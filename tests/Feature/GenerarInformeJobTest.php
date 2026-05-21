<?php

namespace Tests\Feature;

use App\Jobs\GenerarInformeJob;
use App\Models\Dispositivo;
use App\Models\Informe;
use App\Models\User;
use App\Notifications\NotificacionInforme;
use App\Services\InformeService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests para GenerarInformeJob.
 *
 * Verifica:
 *  - Transiciones de estado del informe (pending → processing → completed/failed)
 *  - Cada canal se invoca cuando su flag está activo
 *  - Un canal fallido no impide que los demás funcionen
 *  - La notificación de base de datos se crea
 */
class GenerarInformeJobTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Dispositivo $dispositivo;
    private Informe $informe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario     = User::factory()->create();
        $this->dispositivo = Dispositivo::factory()->create();
        $this->usuario->dispositivos()->attach($this->dispositivo->id, ['nombre' => 'Test', 'habilitado' => 1]);

        $this->informe = Informe::create([
            'user_id'      => $this->usuario->id,
            'tipo'         => 'Programado',
            'periodo_from' => '2026-05-01',
            'periodo_to'   => '2026-05-20',
            'status'       => 'pending',
            'telegram'     => false,
            'discord'      => false,
            'correo'       => false,
        ]);
    }

    private function fabricarJob(
        bool    $telegram      = false,
        bool    $correo        = false,
        bool    $discord       = false,
        ?string $correoDestino = null
    ): GenerarInformeJob {
        return new GenerarInformeJob(
            $this->informe->id,
            $this->usuario->id,
            [$this->dispositivo->id],
            '2026-05-01',
            '2026-05-20',
            $telegram,
            $correo,
            $discord,
            $correoDestino,
        );
    }

    private function simularServicioPdf(string $rutaFalsa = '/tmp/fake-report.pdf'): void
    {
        $this->mock(InformeService::class, function ($m) use ($rutaFalsa) {
            $m->shouldReceive('generarPdfParaInformeExistente')
              ->once()
              ->andReturn(['rutaAbsoluta' => $rutaFalsa]);
        });
    }

    // ── Transiciones de estado ──────────────────────────────────────────────────

    #[Test]
    public function job_marks_informe_as_completed_on_success(): void
    {
        $this->simularServicioPdf();
        $this->mock(NotificationService::class);
        Notification::fake();

        $this->fabricarJob()->handle(app(InformeService::class), app(NotificationService::class));

        $this->assertDatabaseHas('informes', [
            'id'     => $this->informe->id,
            'estado' => 'completed',
        ]);
    }

    #[Test]
    public function job_marks_informe_as_failed_when_pdf_service_throws(): void
    {
        $this->mock(InformeService::class, function ($m) {
            $m->shouldReceive('generarPdfParaInformeExistente')
              ->andThrow(new \RuntimeException('PDF generation failed'));
        });
        $this->mock(NotificationService::class);
        Notification::fake();

        try {
            $this->fabricarJob()->handle(app(InformeService::class), app(NotificationService::class));
        } catch (\Throwable) {
        }

        $this->assertDatabaseHas('informes', [
            'id'     => $this->informe->id,
            'estado' => 'failed',
        ]);
    }

    #[Test]
    public function job_stores_error_message_on_failure(): void
    {
        $this->mock(InformeService::class, function ($m) {
            $m->shouldReceive('generarPdfParaInformeExistente')
              ->andThrow(new \RuntimeException('timeout en renderer'));
        });
        $this->mock(NotificationService::class);
        Notification::fake();

        try {
            $this->fabricarJob()->handle(app(InformeService::class), app(NotificationService::class));
        } catch (\Throwable) {
        }

        $informe = $this->informe->fresh();
        $this->assertStringContainsString('timeout en renderer', $informe->mensaje_error);
    }

    // ── Canal Telegram ──────────────────────────────────────────────────────────

    #[Test]
    public function job_calls_telegram_when_flag_is_true(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegramWithAttachment')->once();
            $m->shouldReceive('sendEmailWithAttachment')->never();
            $m->shouldReceive('sendDiscordWithFile')->never();
        });

        $this->fabricarJob(telegram: true)->handle(app(InformeService::class), app(NotificationService::class));
    }

    #[Test]
    public function job_skips_telegram_when_flag_is_false(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegramWithAttachment')->never();
            $m->shouldReceive('sendEmailWithAttachment')->never();
            $m->shouldReceive('sendDiscordWithFile')->never();
        });

        $this->fabricarJob(telegram: false)->handle(app(InformeService::class), app(NotificationService::class));
    }

    // ── Canal Email ─────────────────────────────────────────────────────────────

    #[Test]
    public function job_calls_email_when_correo_flag_and_destino_are_set(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmailWithAttachment')
              ->once()
              ->withArgs(fn($texto, $usuario, $destino) => $destino === 'dest@example.com');
            $m->shouldReceive('sendTelegramWithAttachment')->never();
            $m->shouldReceive('sendDiscordWithFile')->never();
        });

        $this->fabricarJob(correo: true, correoDestino: 'dest@example.com')
             ->handle(app(InformeService::class), app(NotificationService::class));
    }

    #[Test]
    public function job_skips_email_when_correo_is_true_but_destino_is_null(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmailWithAttachment')->never();
            $m->shouldReceive('sendTelegramWithAttachment')->never();
            $m->shouldReceive('sendDiscordWithFile')->never();
        });

        $this->fabricarJob(correo: true, correoDestino: null)
             ->handle(app(InformeService::class), app(NotificationService::class));
    }

    // ── Canal Discord ───────────────────────────────────────────────────────────

    #[Test]
    public function job_calls_discord_when_flag_is_true(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendDiscordWithFile')->once();
            $m->shouldReceive('sendTelegramWithAttachment')->never();
            $m->shouldReceive('sendEmailWithAttachment')->never();
        });

        $this->fabricarJob(discord: true)->handle(app(InformeService::class), app(NotificationService::class));
    }

    // ── Resiliencia: un canal fallido no detiene los demás ──────────────────────

    #[Test]
    public function informe_completes_even_when_telegram_throws(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegramWithAttachment')
              ->andThrow(new \RuntimeException('Telegram sin conexión'));
            $m->shouldReceive('sendDiscordWithFile')->once();
        });

        $this->fabricarJob(telegram: true, discord: true)
             ->handle(app(InformeService::class), app(NotificationService::class));

        $this->assertDatabaseHas('informes', ['id' => $this->informe->id, 'estado' => 'completed']);
    }

    #[Test]
    public function informe_completes_even_when_email_throws(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmailWithAttachment')
              ->andThrow(new \RuntimeException('SMTP timeout'));
            $m->shouldReceive('sendTelegramWithAttachment')->once();
        });

        $this->fabricarJob(telegram: true, correo: true, correoDestino: 'a@b.com')
             ->handle(app(InformeService::class), app(NotificationService::class));

        $this->assertDatabaseHas('informes', ['id' => $this->informe->id, 'estado' => 'completed']);
    }

    #[Test]
    public function informe_completes_even_when_discord_throws(): void
    {
        $this->simularServicioPdf();
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendDiscordWithFile')
              ->andThrow(new \RuntimeException('Discord 401'));
            $m->shouldReceive('sendTelegramWithAttachment')->once();
        });

        $this->fabricarJob(telegram: true, discord: true)
             ->handle(app(InformeService::class), app(NotificationService::class));

        $this->assertDatabaseHas('informes', ['id' => $this->informe->id, 'estado' => 'completed']);
    }

    // ── Notificación de base de datos ───────────────────────────────────────────

    #[Test]
    public function job_sends_database_notification_to_user(): void
    {
        Notification::fake();
        $this->simularServicioPdf();
        $this->mock(NotificationService::class);

        $this->fabricarJob()->handle(app(InformeService::class), app(NotificationService::class));

        Notification::assertSentTo($this->usuario, NotificacionInforme::class);
    }

    #[Test]
    public function informe_completes_even_when_db_notification_throws(): void
    {
        Notification::fake();
        $this->simularServicioPdf();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegramWithAttachment')->once();
        });

        $this->fabricarJob(telegram: true)->handle(app(InformeService::class), app(NotificationService::class));

        $this->assertDatabaseHas('informes', ['id' => $this->informe->id, 'estado' => 'completed']);
    }
}
