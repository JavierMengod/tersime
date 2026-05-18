<?php

namespace Tests\Feature\Web;

use App\Models\AlertLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── Acceso ─────────────────────────────────────────────────────────────────

    #[Test]
    public function non_admin_cannot_access_sistema(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]))
             ->get(route('configuracion.sistema'))
             ->assertStatus(403);
    }

    #[Test]
    public function non_admin_cannot_access_conexiones(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]))
             ->get(route('configuracion.conexiones'))
             ->assertStatus(403);
    }

    #[Test]
    public function non_admin_cannot_access_logs(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]))
             ->get(route('configuracion.logs'))
             ->assertStatus(403);
    }

    #[Test]
    public function admin_can_access_sistema(): void
    {
        Setting::set('alert_log_retention_days', '90');
        Setting::set('report_retention_days', '180');

        $this->actingAs(User::factory()->admin()->create())
             ->get(route('configuracion.sistema'))
             ->assertStatus(200);
    }

    #[Test]
    public function all_users_can_access_cuenta(): void
    {
        $this->actingAs(User::factory()->create())
             ->get(route('configuracion.cuenta'))
             ->assertStatus(200);
    }

    // ── Cuenta: preferencias ───────────────────────────────────────────────────

    #[Test]
    public function update_cuenta_saves_language_theme_and_timezone(): void
    {
        $user = User::factory()->create(['language' => 'es', 'theme' => 'light']);

        $this->actingAs($user)->post(route('configuracion.cuenta.preferencias'), [
            'language' => 'en',
            'theme'    => 'dark',
            'timezone' => 'UTC',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('en', $user->language);
        $this->assertSame('dark', $user->theme);
        $this->assertSame('UTC', $user->timezone);
    }

    #[Test]
    public function update_cuenta_validates_language_must_be_valid(): void
    {
        $this->actingAs(User::factory()->create())
             ->post(route('configuracion.cuenta.preferencias'), [
                 'language' => 'klingon',
                 'theme'    => 'light',
                 'timezone' => 'UTC',
             ])->assertSessionHasErrors('language');
    }

    #[Test]
    public function update_cuenta_validates_theme_must_be_light_or_dark(): void
    {
        $this->actingAs(User::factory()->create())
             ->post(route('configuracion.cuenta.preferencias'), [
                 'language' => 'es',
                 'theme'    => 'solarized',
                 'timezone' => 'UTC',
             ])->assertSessionHasErrors('theme');
    }

    // ── Cuenta: contraseña ─────────────────────────────────────────────────────

    #[Test]
    public function update_cuenta_changes_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('old_password')]);

        $this->actingAs($user)->post(route('configuracion.cuenta.password'), [
            'current_password'          => 'old_password',
            'new_password'              => 'new_secure_password',
            'new_password_confirmation' => 'new_secure_password',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('new_secure_password', $user->fresh()->password));
    }

    #[Test]
    public function update_cuenta_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);

        $this->actingAs($user)->post(route('configuracion.cuenta.password'), [
            'current_password'          => 'wrong',
            'new_password'              => 'new_password',
            'new_password_confirmation' => 'new_password',
        ])->assertSessionHasErrors('current_password');
    }

    #[Test]
    public function update_cuenta_rejects_mismatched_new_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('current')]);

        $this->actingAs($user)->post(route('configuracion.cuenta.password'), [
            'current_password'          => 'current',
            'new_password'              => 'new_pass',
            'new_password_confirmation' => 'different_pass',
        ])->assertSessionHasErrors('new_password');
    }

    // ── Sistema: configuración ─────────────────────────────────────────────────

    #[Test]
    public function admin_can_update_retention_settings(): void
    {
        Setting::set('alert_log_retention_days', '90');
        Setting::set('report_retention_days', '180');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('configuracion.sistema.update'), [
            'alert_log_retention_days' => 30,
            'report_retention_days'    => 60,
        ])->assertRedirect();

        $this->assertSame('30', Setting::get('alert_log_retention_days'));
        $this->assertSame('60', Setting::get('report_retention_days'));
    }

    #[Test]
    public function non_admin_cannot_post_to_sistema(): void
    {
        $this->actingAs(User::factory()->create())
             ->post(route('configuracion.sistema.update'), [
                 'alert_log_retention_days' => 30,
                 'report_retention_days'    => 60,
             ])->assertStatus(403);
    }

    // ── Sistema: purga de alertas ──────────────────────────────────────────────

    #[Test]
    public function purgar_alertas_deletes_logs_older_than_retention_days(): void
    {
        Setting::set('alert_log_retention_days', '30');

        $user  = User::factory()->admin()->create();

        // Log antiguo (fuera de retención)
        AlertLog::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        // Log reciente (dentro de retención)
        $recent = AlertLog::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($user)
             ->post(route('configuracion.sistema.purgar_alertas'))
             ->assertRedirect();

        $this->assertDatabaseHas('alert_logs', ['id' => $recent->id]);
        $this->assertDatabaseCount('alert_logs', 1);
    }

    #[Test]
    public function purgar_alertas_deletes_all_when_retention_is_zero(): void
    {
        Setting::set('alert_log_retention_days', '0');
        $user = User::factory()->admin()->create();

        AlertLog::factory()->count(5)->create([
            'user_id'    => $user->id,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $this->actingAs($user)
             ->post(route('configuracion.sistema.purgar_alertas'))
             ->assertRedirect();

        $this->assertDatabaseCount('alert_logs', 0);
    }

    // ── Conexiones: tokens sensibles ───────────────────────────────────────────

    #[Test]
    public function update_conexiones_does_not_overwrite_influx_token_when_empty(): void
    {
        Setting::set('influxdb_url', 'http://influx:8086');
        Setting::set('influxdb_org', 'myorg');
        Setting::set('influxdb_bucket', 'mybucket');
        Setting::set('influxdb_token', 'secret-token-original');
        Setting::set('grafana_base_url', 'http://grafana:3000');
        Setting::set('grafana_datasource_id', '1');
        Setting::set('grafana_api_key', 'gf-key');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('configuracion.conexiones.update'), [
            'influxdb_url'            => 'http://influx:8086',
            'influxdb_org'            => 'myorg',
            'influxdb_bucket'         => 'mybucket',
            'influxdb_token'          => '',      // vacío → no sobreescribir
            'grafana_base_url'        => 'http://grafana:3000',
            'grafana_datasource_id'   => 1,
            'grafana_api_key'         => '',      // vacío → no sobreescribir
            'grafana_renderer_url'    => '',
            'predictor_url'           => '',
            'predictor_timeout'       => 120,
            'predictor_default_hours' => 24,
            'openrouter_model'        => '',
            'openrouter_api_key'      => '',
        ])->assertRedirect();

        $this->assertSame('secret-token-original', Setting::get('influxdb_token'));
        $this->assertSame('gf-key', Setting::get('grafana_api_key'));
    }

    #[Test]
    public function update_conexiones_overwrites_token_when_new_value_provided(): void
    {
        Setting::set('influxdb_url', 'http://influx:8086');
        Setting::set('influxdb_org', 'myorg');
        Setting::set('influxdb_bucket', 'mybucket');
        Setting::set('influxdb_token', 'old-token');
        Setting::set('grafana_base_url', 'http://grafana:3000');
        Setting::set('grafana_datasource_id', '1');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('configuracion.conexiones.update'), [
            'influxdb_url'            => 'http://influx:8086',
            'influxdb_org'            => 'myorg',
            'influxdb_bucket'         => 'mybucket',
            'influxdb_token'          => 'new-secret-token',
            'grafana_base_url'        => 'http://grafana:3000',
            'grafana_datasource_id'   => 1,
            'grafana_api_key'         => '',
            'grafana_renderer_url'    => '',
            'predictor_url'           => '',
            'predictor_timeout'       => 120,
            'predictor_default_hours' => 24,
            'openrouter_model'        => '',
            'openrouter_api_key'      => '',
        ])->assertRedirect();

        $this->assertSame('new-secret-token', Setting::get('influxdb_token'));
    }
}
