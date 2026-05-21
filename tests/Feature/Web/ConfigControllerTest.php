<?php

namespace Tests\Feature\Web;

use App\Models\RegistroAlerta;
use App\Models\Ajuste;
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
        Ajuste::set('alert_log_retention_days', '90');
        Ajuste::set('report_retention_days', '180');

        $this->actingAs(User::factory()->administrador()->create())
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

    // ── Perfil: datos personales ───────────────────────────────────────────────

    #[Test]
    public function update_perfil_saves_name_email_and_language(): void
    {
        $user = User::factory()->create(['name' => 'Antiguo', 'language' => 'es']);

        $this->actingAs($user)->post(route('configuracion.perfil.update'), [
            'name'     => 'Nuevo Nombre',
            'email'    => $user->email,
            'language' => 'en',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertSame('en', $user->language);
    }

    #[Test]
    public function update_perfil_validates_language_must_be_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('configuracion.perfil.update'), [
            'name'     => $user->name,
            'email'    => $user->email,
            'language' => 'klingon',
        ])->assertSessionHasErrors('language');
    }

    // ── Ajustes: apariencia ────────────────────────────────────────────────────

    #[Test]
    public function update_ajustes_saves_theme_timezone_and_coste_kwh(): void
    {
        $user = User::factory()->create(['theme' => 'light']);

        $this->actingAs($user)->post(route('configuracion.ajustes.update'), [
            'theme'     => 'dark',
            'timezone'  => 'UTC',
            'coste_kwh' => 0.25,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('dark', $user->theme);
        $this->assertSame('UTC', $user->timezone);
        $this->assertEquals(0.25, (float) $user->coste_kwh);
    }

    #[Test]
    public function update_ajustes_validates_theme_must_be_light_or_dark(): void
    {
        $this->actingAs(User::factory()->create())
             ->post(route('configuracion.ajustes.update'), [
                 'theme'     => 'solarized',
                 'timezone'  => 'UTC',
                 'coste_kwh' => 0.15,
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
        Ajuste::set('alert_log_retention_days', '90');
        Ajuste::set('report_retention_days', '180');

        $admin = User::factory()->administrador()->create();

        $this->actingAs($admin)->post(route('configuracion.sistema.update'), [
            'alert_log_retention_days' => 30,
            'report_retention_days'    => 60,
        ])->assertRedirect();

        $this->assertSame('30', Ajuste::get('alert_log_retention_days'));
        $this->assertSame('60', Ajuste::get('report_retention_days'));
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
        Ajuste::set('alert_log_retention_days', '30');

        $user  = User::factory()->administrador()->create();

        // Log antiguo (fuera de retención)
        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(60),
        ]);

        // Log reciente (dentro de retención)
        $recent = RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $this->actingAs($user)
             ->post(route('configuracion.sistema.purgar_alertas'))
             ->assertRedirect();

        $this->assertDatabaseHas('registros_alerta', ['id' => $recent->id]);
        $this->assertDatabaseCount('registros_alerta', 1);
    }

    #[Test]
    public function purgar_alertas_deletes_all_when_retention_is_zero(): void
    {
        Ajuste::set('alert_log_retention_days', '0');
        $user = User::factory()->administrador()->create();

        RegistroAlerta::factory()->count(5)->create([
            'user_id'    => $user->id,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $this->actingAs($user)
             ->post(route('configuracion.sistema.purgar_alertas'))
             ->assertRedirect();

        $this->assertDatabaseCount('registros_alerta', 0);
    }

    // ── Conexiones: tokens sensibles ───────────────────────────────────────────

    #[Test]
    public function update_conexiones_does_not_overwrite_influx_token_when_empty(): void
    {
        Ajuste::set('influxdb_url', 'http://influx:8086');
        Ajuste::set('influxdb_org', 'myorg');
        Ajuste::set('influxdb_bucket', 'mybucket');
        Ajuste::set('influxdb_token', 'secret-token-original');
        Ajuste::set('grafana_base_url', 'http://grafana:3000');
        Ajuste::set('grafana_datasource_id', '1');
        Ajuste::set('grafana_api_key', 'gf-key');

        $admin = User::factory()->administrador()->create();

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

        $this->assertSame('secret-token-original', Ajuste::get('influxdb_token'));
        $this->assertSame('gf-key', Ajuste::get('grafana_api_key'));
    }

    #[Test]
    public function update_conexiones_overwrites_token_when_new_value_provided(): void
    {
        Ajuste::set('influxdb_url', 'http://influx:8086');
        Ajuste::set('influxdb_org', 'myorg');
        Ajuste::set('influxdb_bucket', 'mybucket');
        Ajuste::set('influxdb_token', 'old-token');
        Ajuste::set('grafana_base_url', 'http://grafana:3000');
        Ajuste::set('grafana_datasource_id', '1');

        $admin = User::factory()->administrador()->create();

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

        $this->assertSame('new-secret-token', Ajuste::get('influxdb_token'));
    }
}
