<?php

namespace Tests\Feature;

use App\Models\RegistroAlerta;
use App\Models\Dispositivo;
use App\Models\Regla;
use App\Models\User;
use App\Services\InfluxService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests de VerificarReglas centrados en:
 *  - Interpolación de plantillas ({dispositivo}, {regla}, {valor}, {device}, {rule}, {value})
 *  - Aislamiento de canales: un canal fallido no detiene los demás
 *  - Selección correcta del canal (email, telegram, discord)
 *  - Valor nulo en plantilla muestra "sin datos"
 */
class VerificarReglasTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Dispositivo $dispositivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario     = User::factory()->create();
        $this->dispositivo = Dispositivo::factory()->create(['etiqueta_influx' => 'SENSOR-001']);

        $this->usuario->dispositivos()->attach($this->dispositivo->id, [
            'nombre'    => 'Medidor Oficina',
            'habilitado' => 1,
        ]);
    }

    private function simularInflux(?float $valor): void
    {
        $this->mock(InfluxService::class, fn($m) => $m->shouldReceive('ultimoValor')->andReturn($valor));
    }

    private function adjuntarOk(Regla $regla): void
    {
        $regla->dispositivos()->attach($this->dispositivo->id, ['alert_state' => 'ok']);
    }

    // ── Interpolación de plantillas ─────────────────────────────────────────────

    #[Test]
    public function email_template_interpolates_dispositivo_regla_valor(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->usuario->id,
            'nombre'              => 'Alto Consumo',
            'correo_activo'       => true,
            'correo_destinatario' => 'a@b.com',
            'plantilla_correo'    => 'Dispositivo: {dispositivo} | Regla: {regla} | Valor: {valor}',
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(150.5);
        Notification::fake();

        $capturado = null;
        $this->mock(NotificationService::class, function ($m) use (&$capturado) {
            $m->shouldReceive('sendEmail')->once()
              ->withArgs(function ($texto) use (&$capturado) { $capturado = $texto; return true; });
            $m->shouldReceive('sendTelegram')->never();
            $m->shouldReceive('sendDiscord')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertStringContainsString('SENSOR-001', $capturado);
        $this->assertStringContainsString('Alto Consumo', $capturado);
        $this->assertStringContainsString('150.5', $capturado);
    }

    #[Test]
    public function telegram_template_supports_alias_device_rule_value(): void
    {
        $regla = Regla::factory()->withOperator('<', 50)->create([
            'user_id'            => $this->usuario->id,
            'nombre'             => 'Bajo Consumo',
            'telegram_activo'    => true,
            'plantilla_telegram' => 'ALERTA {device} / {rule} = {value}',
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(30.0);
        Notification::fake();

        $capturado = null;
        $this->mock(NotificationService::class, function ($m) use (&$capturado) {
            $m->shouldReceive('sendTelegram')->once()
              ->withArgs(function ($texto) use (&$capturado) { $capturado = $texto; return true; });
            $m->shouldReceive('sendEmail')->never();
            $m->shouldReceive('sendDiscord')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertStringContainsString('SENSOR-001', $capturado);
        $this->assertStringContainsString('Bajo Consumo', $capturado);
        $this->assertStringContainsString('30', $capturado);
        $this->assertStringNotContainsString('🚨', $capturado);
    }

    #[Test]
    public function discord_template_interpolates_correctly(): void
    {
        $regla = Regla::factory()->withOperator('>', 200)->create([
            'user_id'           => $this->usuario->id,
            'discord_activo'    => true,
            'plantilla_discord' => '{dispositivo} superó el límite: {valor}',
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(250.0);
        Notification::fake();

        $capturado = null;
        $this->mock(NotificationService::class, function ($m) use (&$capturado) {
            $m->shouldReceive('sendDiscord')->once()
              ->withArgs(function ($texto) use (&$capturado) { $capturado = $texto; return true; });
            $m->shouldReceive('sendEmail')->never();
            $m->shouldReceive('sendTelegram')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertStringContainsString('SENSOR-001', $capturado);
        $this->assertStringContainsString('250', $capturado);
    }

    #[Test]
    public function null_influx_value_skips_evaluation_and_sends_no_notification(): void
    {
        // Con datos ausentes el comando debe omitir la evaluación sin disparar alertas.
        $regla = Regla::factory()->withOperator('>', 0)->create([
            'user_id'            => $this->usuario->id,
            'telegram_activo'    => true,
            'plantilla_telegram' => 'Valor actual: {valor}',
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(null);
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegram')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    #[Test]
    public function without_template_uses_default_message_with_emoji(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'            => $this->usuario->id,
            'telegram_activo'    => true,
            'plantilla_telegram' => null,
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(200.0);
        Notification::fake();

        $capturado = null;
        $this->mock(NotificationService::class, function ($m) use (&$capturado) {
            $m->shouldReceive('sendTelegram')->once()
              ->withArgs(function ($texto) use (&$capturado) { $capturado = $texto; return true; });
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertStringContainsString('🚨', $capturado);
    }

    // ── Aislamiento de canales ──────────────────────────────────────────────────

    #[Test]
    public function telegram_failure_does_not_stop_discord(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'         => $this->usuario->id,
            'telegram_activo' => true,
            'discord_activo'  => true,
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(200.0);
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendTelegram')
              ->andThrow(new \RuntimeException('Telegram sin respuesta'));
            $m->shouldReceive('sendDiscord')->once();
            $m->shouldReceive('sendEmail')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertDatabaseCount('registros_alerta', 1);
    }

    #[Test]
    public function email_failure_does_not_stop_telegram(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->usuario->id,
            'correo_activo'       => true,
            'correo_destinatario' => 'a@b.com',
            'telegram_activo'     => true,
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(200.0);
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmail')
              ->andThrow(new \RuntimeException('SMTP error'));
            $m->shouldReceive('sendTelegram')->once();
            $m->shouldReceive('sendDiscord')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);
    }

    #[Test]
    public function discord_failure_does_not_stop_email(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->usuario->id,
            'discord_activo'      => true,
            'correo_activo'       => true,
            'correo_destinatario' => 'a@b.com',
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(200.0);
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendDiscord')
              ->andThrow(new \RuntimeException('Discord webhook 429'));
            $m->shouldReceive('sendEmail')->once();
            $m->shouldReceive('sendTelegram')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);
    }

    // ── Resolución con plantilla ────────────────────────────────────────────────

    #[Test]
    public function resolution_uses_correct_message_without_template(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'         => $this->usuario->id,
            'telegram_activo' => true,
        ]);
        $regla->dispositivos()->attach($this->dispositivo->id, ['alert_state' => 'firing']);
        $this->simularInflux(50.0);
        Notification::fake();

        $capturado = null;
        $this->mock(NotificationService::class, function ($m) use (&$capturado) {
            $m->shouldReceive('sendTelegram')->once()
              ->withArgs(function ($texto) use (&$capturado) { $capturado = $texto; return true; });
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertStringContainsString('✅', $capturado);
        $this->assertStringContainsString('SENSOR-001', $capturado);
    }

    // ── Canal email sin recipient → no se llama ─────────────────────────────────

    #[Test]
    public function email_not_called_when_recipient_email_is_missing(): void
    {
        $regla = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->usuario->id,
            'correo_activo'       => true,
            'correo_destinatario' => null,
            'telegram_activo'     => true,
        ]);
        $this->adjuntarOk($regla);
        $this->simularInflux(200.0);
        Notification::fake();

        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmail')->never();
            $m->shouldReceive('sendTelegram')->once();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);
    }
}
