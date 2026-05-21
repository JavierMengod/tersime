<?php

namespace Tests\Unit\Models;

use App\Models\ProgramacionInformes;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests para ProgramacionInformes::proximaEjecucion().
 *
 * Casos clave:
 *  - Primera ejecución (last_run_at null) con y sin hora_inicio
 *  - Ejecuciones sucesivas (last_run_at set)
 *  - Tipos: horas, dias, meses
 *  - hora_inicio aún no llegó hoy vs. ya pasó hoy
 */
class ProgramacionInformesTest extends TestCase
{
    // ── Tipo horas ──────────────────────────────────────────────────────────────

    #[Test]
    public function hourly_without_last_run_returns_now(): void
    {
        $ahora = Carbon::create(2026, 5, 20, 10, 15);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 1,
            'hora_inicio'   => null,
        ]);

        $this->assertTrue($p->proximaEjecucion($ahora)->eq($ahora));
    }

    #[Test]
    public function hourly_with_last_run_adds_interval(): void
    {
        $lastRun = Carbon::create(2026, 5, 20, 8, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'horas',
            'valor_periodo' => 3,
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-05-20 11:00:00', $proxima->toDateTimeString());
    }

    // ── Primera ejecución con hora_inicio: aún no llegó ────────────────────────

    #[Test]
    public function daily_without_last_run_schedules_today_when_hora_not_yet_passed(): void
    {
        // 08:00, hora_inicio 09:00 → primera ejecución hoy a las 09:00
        $ahora = Carbon::create(2026, 5, 20, 8, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);

        $proxima = $p->proximaEjecucion($ahora);

        $this->assertSame('2026-05-20 09:00:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function monthly_without_last_run_schedules_today_when_hora_not_yet_passed(): void
    {
        $ahora = Carbon::create(2026, 5, 20, 7, 30);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);

        $proxima = $p->proximaEjecucion($ahora);

        $this->assertSame('2026-05-20 09:00:00', $proxima->toDateTimeString());
    }

    // ── Primera ejecución con hora_inicio: ya pasó hoy ─────────────────────────

    #[Test]
    public function daily_without_last_run_schedules_tomorrow_when_hora_already_passed(): void
    {
        // 10:15, hora_inicio 09:00 → primera ejecución mañana a las 09:00
        $ahora = Carbon::create(2026, 5, 20, 10, 15);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);

        $proxima = $p->proximaEjecucion($ahora);

        $this->assertSame('2026-05-21 09:00:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function daily_without_last_run_schedules_tomorrow_when_hora_is_exactly_now(): void
    {
        // Las 09:00 exactas: no es "mayor que", así que ya pasó → mañana
        $ahora = Carbon::create(2026, 5, 20, 9, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);

        $proxima = $p->proximaEjecucion($ahora);

        $this->assertSame('2026-05-21 09:00:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function monthly_without_last_run_schedules_next_month_when_hora_already_passed(): void
    {
        $ahora = Carbon::create(2026, 5, 20, 11, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);

        $proxima = $p->proximaEjecucion($ahora);

        $this->assertSame('2026-06-20 09:00:00', $proxima->toDateTimeString());
    }

    // ── Sin hora_inicio: primera ejecución es ahora ─────────────────────────────

    #[Test]
    public function daily_without_last_run_and_no_hora_inicio_returns_now(): void
    {
        $ahora = Carbon::create(2026, 5, 20, 10, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => null,
        ]);

        $this->assertTrue($p->proximaEjecucion($ahora)->eq($ahora));
    }

    // ── Ejecuciones sucesivas con last_run_at ───────────────────────────────────

    #[Test]
    public function daily_with_last_run_adds_one_day_at_hora_inicio(): void
    {
        $lastRun = Carbon::create(2026, 5, 20, 9, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 1,
            'hora_inicio'   => '09:00',
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-05-21 09:00:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function every_7_days_with_last_run_adds_7_days(): void
    {
        $lastRun = Carbon::create(2026, 5, 20, 9, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 7,
            'hora_inicio'   => '09:00',
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-05-27 09:00:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function monthly_with_last_run_adds_months_at_hora_inicio(): void
    {
        $lastRun = Carbon::create(2026, 5, 1, 9, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 1,
            'hora_inicio'   => '08:30',
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-06-01 08:30:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function daily_without_hora_inicio_preserves_time_of_last_run(): void
    {
        $lastRun = Carbon::create(2026, 5, 20, 14, 30);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'dias',
            'valor_periodo' => 2,
            'hora_inicio'   => null,
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-05-22 14:30:00', $proxima->toDateTimeString());
    }

    #[Test]
    public function every_3_months_with_last_run(): void
    {
        $lastRun = Carbon::create(2026, 1, 15, 9, 0);
        $p = new ProgramacionInformes([
            'tipo_periodo'  => 'meses',
            'valor_periodo' => 3,
            'hora_inicio'   => '09:00',
        ]);
        $p->ultima_ejecucion_at = $lastRun;

        $proxima = $p->proximaEjecucion();

        $this->assertSame('2026-04-15 09:00:00', $proxima->toDateTimeString());
    }
}
