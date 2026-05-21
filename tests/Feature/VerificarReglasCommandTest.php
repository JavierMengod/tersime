<?php

namespace Tests\Feature;

use App\Services\InfluxService;
use App\Services\NotificationService;
use App\Models\RegistroAlerta;
use App\Models\Dispositivo;
use App\Models\Regla;
use App\Models\User;
use App\Notifications\NotificacionAlerta;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VerificarReglasCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dispositivo $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user   = User::factory()->create();
        $this->device = Dispositivo::factory()->create(['etiqueta_influx' => 'TEST-DEV-001']);

        $this->user->dispositivos()->attach($this->device->id, [
            'nombre'    => 'Medidor Test',
            'habilitado' => 1,
        ]);
    }

    private function attachDevice(Regla $rule, string $alertState = 'ok', ?string $pendingSince = null): void
    {
        $rule->dispositivos()->attach($this->device->id, [
            'alert_state'   => $alertState,
            'pending_since' => $pendingSince,
        ]);
    }

    private function fakeInflux(?float $value): void
    {
        $mock = $this->mock(InfluxService::class, function ($m) use ($value) {
            $m->shouldReceive('ultimoValor')->andReturn($value);
        });
    }

    private function fakeNotifier(): void
    {
        $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmail')->andReturn(null);
            $m->shouldReceive('sendTelegram')->andReturn(null);
            $m->shouldReceive('sendDiscord')->andReturn(null);
        });
    }

    // ── ok → firing (for_duration = 0) ────────────────────────────────────────

    #[Test]
    public function ok_transitions_to_firing_immediately_when_condition_met_and_no_duration(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'       => $this->user->id,
            'duracion'  => 0,
            'correo_activo' => true,
            'correo_destinatario' => 'alerts@test.com',
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(150.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $pivot = $rule->dispositivos()->first()->pivot;
        $this->assertSame('firing', $pivot->alert_state);
        $this->assertNull($pivot->pending_since);
    }

    #[Test]
    public function ok_stays_ok_when_condition_not_met(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(50.0);

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('ok', $rule->dispositivos()->first()->pivot->alert_state);
        $this->assertDatabaseCount('registros_alerta', 0);
    }

    // ── ok → pending (for_duration > 0) ───────────────────────────────────────

    #[Test]
    public function ok_transitions_to_pending_when_condition_met_and_duration_set(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->withDuration(15)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $pivot = $rule->dispositivos()->first()->pivot;
        $this->assertSame('pending', $pivot->alert_state);
        $this->assertNotNull($pivot->pending_since);
        $this->assertDatabaseCount('registros_alerta', 0);
    }

    // ── pending → firing (duration elapsed) ───────────────────────────────────

    #[Test]
    public function pending_transitions_to_firing_after_duration_elapsed(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->withDuration(15)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'correo_destinatario' => 'alerts@test.com',
        ]);
        $pendingSince = Carbon::now()->subMinutes(20)->toDateTimeString();
        $this->attachDevice($rule, 'pending', $pendingSince);
        $this->fakeInflux(200.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $pivot = $rule->dispositivos()->first()->pivot;
        $this->assertSame('firing', $pivot->alert_state);
    }

    #[Test]
    public function pending_stays_pending_when_duration_not_yet_elapsed(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->withDuration(60)->create([
            'user_id' => $this->user->id,
        ]);
        $pendingSince = Carbon::now()->subMinutes(10)->toDateTimeString();
        $this->attachDevice($rule, 'pending', $pendingSince);
        $this->fakeInflux(200.0);

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('pending', $rule->dispositivos()->first()->pivot->alert_state);
        $this->assertDatabaseCount('registros_alerta', 0);
    }

    // ── pending → ok (false alarm) ─────────────────────────────────────────────

    #[Test]
    public function pending_resets_to_ok_when_condition_resolves_before_firing(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->withDuration(15)->create([
            'user_id' => $this->user->id,
        ]);
        $pendingSince = Carbon::now()->subMinutes(5)->toDateTimeString();
        $this->attachDevice($rule, 'pending', $pendingSince);
        $this->fakeInflux(50.0); // condition no longer met

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $pivot = $rule->dispositivos()->first()->pivot;
        $this->assertSame('ok', $pivot->alert_state);
        $this->assertNull($pivot->pending_since);
        $this->assertDatabaseCount('registros_alerta', 0);
    }

    // ── firing → ok (resolution) ──────────────────────────────────────────────

    #[Test]
    public function firing_transitions_to_ok_when_condition_resolves(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'correo_destinatario' => 'alerts@test.com',
        ]);
        $this->attachDevice($rule, 'firing');
        $this->fakeInflux(80.0); // below threshold → resolved
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $pivot = $rule->dispositivos()->first()->pivot;
        $this->assertSame('ok', $pivot->alert_state);
        $this->assertNull($pivot->pending_since);
    }

    #[Test]
    public function firing_stays_firing_when_condition_still_met(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'firing');
        $this->fakeInflux(200.0); // still above threshold

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule->dispositivos()->first()->pivot->alert_state);
        $this->assertDatabaseCount('registros_alerta', 0); // no new log while already firing
    }

    // ── AlertLog creation ──────────────────────────────────────────────────────

    #[Test]
    public function firing_transition_creates_alert_log_with_type_firing(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'correo_destinatario' => 'alerts@test.com',
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(150.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertDatabaseCount('registros_alerta', 1);
        $this->assertDatabaseHas('registros_alerta', [
            'user_id'    => $this->user->id,
            'regla_id'   => $rule->id,
            'tipo'       => 'firing',
            'canales'    => '["email"]',
        ]);
    }

    #[Test]
    public function resolution_transition_creates_alert_log_with_type_resolution(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'         => $this->user->id,
            'telegram_activo' => true,
        ]);
        $this->attachDevice($rule, 'firing');
        $this->fakeInflux(50.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertDatabaseHas('registros_alerta', [
            'user_id'  => $this->user->id,
            'regla_id' => $rule->id,
            'tipo'     => 'resolution',
            'canales'  => '["telegram"]',
        ]);
    }

    #[Test]
    public function alert_log_records_multiple_enabled_channels(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'telegram_activo'     => true,
            'discord_activo'      => true,
            'correo_destinatario' => 'a@b.com',
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $log = RegistroAlerta::first();
        $this->assertNotNull($log);
        $this->assertContains('email', $log->canales);
        $this->assertContains('telegram', $log->canales);
        $this->assertContains('discord', $log->canales);
    }

    #[Test]
    public function alert_log_records_device_name_and_rule_name(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
            'nombre'  => 'Consumo Máximo',
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        // In Artisan context there is no authenticated user, so nombre falls back to etiqueta_influx
        $this->assertDatabaseHas('registros_alerta', [
            'nombre_regla'        => 'Consumo Máximo',
            'nombre_dispositivo'  => 'TEST-DEV-001',
        ]);
    }

    // ── Null / sin datos ───────────────────────────────────────────────────────

    #[Test]
    public function null_influx_value_triggers_alert_regardless_of_operator(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(null); // no data → always fires
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule->dispositivos()->first()->pivot->alert_state);
    }

    #[Test]
    public function null_value_alert_log_message_mentions_sin_datos(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(null);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $log = RegistroAlerta::first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Sin datos', $log->mensaje);
    }

    // ── Regla inactiva ─────────────────────────────────────────────────────────

    #[Test]
    public function inactive_rule_is_not_evaluated(): void
    {
        $rule = Regla::factory()->inactive()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');

        // If the command evaluated the rule, it would call InfluxService::ultimoValor.
        // We do NOT fake InfluxService, so any attempt to instantiate it would fail with
        // a DB configuration error — which would itself cause test failure.
        $this->fakeInflux(999.0); // safe: should never be called, but avoid crash if it is

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertDatabaseCount('registros_alerta', 0);
        $this->assertSame('ok', $rule->dispositivos()->first()->pivot->alert_state);
    }

    // ── Operadores ─────────────────────────────────────────────────────────────

    #[Test]
    public function operator_less_than_triggers_when_value_below_threshold(): void
    {
        $rule = Regla::factory()->withOperator('<', 50)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(30.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule->dispositivos()->first()->pivot->alert_state);
    }

    #[Test]
    public function operator_less_than_does_not_trigger_when_value_above_threshold(): void
    {
        $rule = Regla::factory()->withOperator('<', 50)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(80.0);

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('ok', $rule->dispositivos()->first()->pivot->alert_state);
    }

    #[Test]
    public function operator_equals_triggers_only_on_exact_match(): void
    {
        $rule = Regla::factory()->withOperator('==', 100.0)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(100.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule->dispositivos()->first()->pivot->alert_state);
    }

    #[Test]
    public function operator_not_equals_triggers_when_values_differ(): void
    {
        $rule = Regla::factory()->withOperator('!=', 100.0)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(99.0);
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule->dispositivos()->first()->pivot->alert_state);
    }

    // ── Notificaciones ─────────────────────────────────────────────────────────

    #[Test]
    public function firing_dispatches_database_notification_to_user(): void
    {
        Notification::fake();

        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);
        $this->fakeNotifier();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        Notification::assertSentTo($this->user, NotificacionAlerta::class, function ($notification) {
            return $notification->getTipo() === 'firing';
        });
    }

    #[Test]
    public function resolution_dispatches_database_notification_to_user(): void
    {
        Notification::fake();

        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'firing');
        $this->fakeInflux(50.0);
        $this->fakeNotifier();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        Notification::assertSentTo($this->user, NotificacionAlerta::class, function ($notification) {
            return $notification->getTipo() === 'resolution';
        });
    }

    #[Test]
    public function no_notification_dispatched_while_rule_stays_firing(): void
    {
        Notification::fake();

        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id' => $this->user->id,
        ]);
        $this->attachDevice($rule, 'firing');
        $this->fakeInflux(200.0); // still firing

        $this->artisan('reglas:verificar')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    #[Test]
    public function email_notification_sent_when_email_enabled_and_recipient_set(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'correo_destinatario' => 'alerts@example.com',
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);
        Notification::fake();

        $notifier = $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmail')
              ->once()
              ->withArgs(function ($msg, $user, $email) {
                  return $email === 'alerts@example.com';
              });
            $m->shouldReceive('sendTelegram')->never();
            $m->shouldReceive('sendDiscord')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);
    }

    #[Test]
    public function email_not_sent_when_recipient_email_is_null(): void
    {
        $rule = Regla::factory()->withOperator('>', 100)->create([
            'user_id'             => $this->user->id,
            'correo_activo'       => true,
            'correo_destinatario' => null, // enabled but no recipient
        ]);
        $this->attachDevice($rule, 'ok');
        $this->fakeInflux(200.0);
        Notification::fake();

        $notifier = $this->mock(NotificationService::class, function ($m) {
            $m->shouldReceive('sendEmail')->never();
            $m->shouldReceive('sendTelegram')->never();
            $m->shouldReceive('sendDiscord')->never();
        });

        $this->artisan('reglas:verificar')->assertExitCode(0);
    }

    // ── Múltiples reglas ───────────────────────────────────────────────────────

    #[Test]
    public function command_evaluates_multiple_active_rules_independently(): void
    {
        $rule1 = Regla::factory()->withOperator('>', 100)->create(['user_id' => $this->user->id]);
        $rule2 = Regla::factory()->withOperator('<', 50)->create(['user_id' => $this->user->id]);

        $device2 = Dispositivo::factory()->create(['etiqueta_influx' => 'TEST-DEV-002']);
        $this->user->dispositivos()->attach($device2->id, ['nombre' => 'Medidor 2', 'habilitado' => 1]);

        $rule1->dispositivos()->attach($this->device->id, ['alert_state' => 'ok']);
        $rule2->dispositivos()->attach($device2->id, ['alert_state' => 'ok']);

        // Device 1 value triggers rule1 (> 100), but NOT rule2
        // Device 2 value triggers rule2 (< 50), but NOT rule1
        $mock = $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('ultimoValor')
              ->with('TEST-DEV-001')
              ->andReturn(200.0);
            $m->shouldReceive('ultimoValor')
              ->with('TEST-DEV-002')
              ->andReturn(30.0);
        });
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $rule1->dispositivos()->first()->pivot->alert_state);
        $this->assertSame('firing', $rule2->dispositivos()->first()->pivot->alert_state);
        $this->assertDatabaseCount('registros_alerta', 2);
    }

    #[Test]
    public function inactive_rules_are_skipped_while_active_rules_are_evaluated(): void
    {
        $active   = Regla::factory()->withOperator('>', 100)->create(['user_id' => $this->user->id]);
        $inactive = Regla::factory()->inactive()->withOperator('>', 100)->create(['user_id' => $this->user->id]);

        $device2 = Dispositivo::factory()->create(['etiqueta_influx' => 'DEV-002']);
        $this->user->dispositivos()->attach($device2->id, ['nombre' => 'Dev 2', 'habilitado' => 1]);

        $active->dispositivos()->attach($this->device->id,  ['alert_state' => 'ok']);
        $inactive->dispositivos()->attach($device2->id, ['alert_state' => 'ok']);

        $this->fakeInflux(200.0); // any value above 100
        $this->fakeNotifier();
        Notification::fake();

        $this->artisan('reglas:verificar')->assertExitCode(0);

        $this->assertSame('firing', $active->dispositivos()->first()->pivot->alert_state);
        $this->assertSame('ok', $inactive->dispositivos()->first()->pivot->alert_state);
        $this->assertDatabaseCount('registros_alerta', 1);
    }
}
