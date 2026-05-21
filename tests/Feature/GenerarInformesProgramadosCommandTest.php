<?php

namespace Tests\Feature;

use App\Jobs\GenerarInformeJob;
use App\Models\Dispositivo;
use App\Models\ProgramacionInformes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests para el comando informes:programados.
 *
 * Verifica:
 *  - Se despacha el job cuando la próxima ejecución ya venció
 *  - No se despacha cuando la próxima ejecución es futura
 *  - Respeto de hora_inicio en primera ejecución y ejecuciones sucesivas
 *  - Programaciones inactivas se ignoran
 *  - last_run_at se actualiza tras despachar
 *  - Sin dispositivos → no se despacha
 */
class GenerarInformesProgramadosCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;
    private Dispositivo $dispositivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario     = User::factory()->create();
        $this->dispositivo = Dispositivo::factory()->create();
        $this->usuario->dispositivos()->attach($this->dispositivo->id, ['nombre' => 'Test', 'habilitado' => 1]);
    }

    private function crearProgramacion(array $atributos = []): ProgramacionInformes
    {
        $p = ProgramacionInformes::create(array_merge([
            'user_id'        => $this->usuario->id,
            'nombre'         => 'Informe Test',
            'tipo_periodo'   => 'horas',
            'valor_periodo'  => 1,
            'hora_inicio'    => null,
            'telegram'       => false,
            'discord'        => false,
            'correo'         => false,
            'correo_destino' => null,
            'activo'         => true,
            'ultima_ejecucion_at'    => null,
        ], $atributos));

        $p->dispositivos()->attach($this->dispositivo->id);

        return $p;
    }

    // ── Despacho básico ─────────────────────────────────────────────────────────

    #[Test]
    public function dispatches_job_when_programacion_never_run_and_tipo_horas(): void
    {
        Queue::fake();
        $this->crearProgramacion(['tipo_periodo' => 'horas', 'valor_periodo' => 1]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertPushed(GenerarInformeJob::class);
    }

    #[Test]
    public function dispatches_job_when_interval_has_elapsed_since_last_run(): void
    {
        Queue::fake();
        $this->crearProgramacion([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 2,
            'ultima_ejecucion_at'   => Carbon::now()->subHours(3),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertPushed(GenerarInformeJob::class);
    }

    #[Test]
    public function does_not_dispatch_when_interval_has_not_elapsed(): void
    {
        Queue::fake();
        $this->crearProgramacion([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 4,
            'ultima_ejecucion_at'   => Carbon::now()->subHours(1),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
    }

    // ── hora_inicio: primera ejecución ─────────────────────────────────────────

    #[Test]
    public function does_not_dispatch_when_hora_inicio_has_not_arrived_today(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 20, 8, 0));

        $this->crearProgramacion([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
            'ultima_ejecucion_at'   => null,
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
        Carbon::setTestNow();
    }

    #[Test]
    public function does_not_dispatch_when_hora_inicio_just_passed_today_with_no_last_run(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 20, 10, 15));

        $this->crearProgramacion([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
            'ultima_ejecucion_at'   => null,
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
        Carbon::setTestNow();
    }

    // ── hora_inicio: ejecuciones sucesivas ─────────────────────────────────────

    #[Test]
    public function dispatches_when_daily_hora_inicio_has_passed_since_last_run(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 20, 10, 0));

        $this->crearProgramacion([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
            'ultima_ejecucion_at'   => Carbon::create(2026, 5, 19, 9, 0),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertPushed(GenerarInformeJob::class);
        Carbon::setTestNow();
    }

    #[Test]
    public function does_not_dispatch_when_daily_next_hora_inicio_is_tomorrow(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 5, 20, 10, 15));

        $this->crearProgramacion([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
            'ultima_ejecucion_at'   => Carbon::create(2026, 5, 20, 9, 0),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
        Carbon::setTestNow();
    }

    #[Test]
    public function dispatches_monthly_when_hora_inicio_passed_on_correct_day(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::create(2026, 6, 1, 10, 0));

        $this->crearProgramacion([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
            'ultima_ejecucion_at'   => Carbon::create(2026, 5, 1, 9, 0),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertPushed(GenerarInformeJob::class);
        Carbon::setTestNow();
    }

    // ── Inactiva ────────────────────────────────────────────────────────────────

    #[Test]
    public function does_not_dispatch_for_inactive_programacion(): void
    {
        Queue::fake();
        $this->crearProgramacion(['activo' => false]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
    }

    // ── Actualización de last_run_at ────────────────────────────────────────────

    #[Test]
    public function updates_last_run_at_after_dispatching(): void
    {
        Queue::fake();
        $programacion = $this->crearProgramacion(['tipo_periodo' => 'horas', 'valor_periodo' => 1]);

        $this->artisan('informes:programados')->assertExitCode(0);

        $programacion->refresh();
        $this->assertNotNull($programacion->ultima_ejecucion_at);
    }

    // ── Sin dispositivos ────────────────────────────────────────────────────────

    #[Test]
    public function skips_programacion_with_no_devices(): void
    {
        Queue::fake();

        ProgramacionInformes::create([
            'user_id'       => $this->usuario->id,
            'nombre'        => 'Sin dispositivos',
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 1,
            'activo'        => true,
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertNotPushed(GenerarInformeJob::class);
    }

    // ── Múltiples programaciones ────────────────────────────────────────────────

    #[Test]
    public function dispatches_only_overdue_programaciones_among_multiple(): void
    {
        Queue::fake();

        $this->crearProgramacion([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 2,
            'ultima_ejecucion_at'   => Carbon::now()->subHours(3),
        ]);

        $this->crearProgramacion([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 4,
            'ultima_ejecucion_at'   => Carbon::now()->subHours(1),
        ]);

        $this->artisan('informes:programados')->assertExitCode(0);

        Queue::assertPushed(GenerarInformeJob::class, 1);
    }
}
