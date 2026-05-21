<?php

namespace Tests\Feature\Api;

use App\Models\Dispositivo;
use App\Models\Regla;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RuleApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function deviceFor(User $user, string $nombre = 'Medidor Test'): Dispositivo
    {
        $device = Dispositivo::factory()->create();
        $user->dispositivos()->attach($device->id, ['nombre' => $nombre, 'habilitado' => 1]);
        return $device;
    }

    private function rulePayload(array $deviceIds, array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Regla de prueba',
            'devices'      => $deviceIds,
            'operator'     => '>',
            'value'        => 100.0,
            'for_duration' => 0,
            'methods'      => ['email'],
            'recipient_email' => 'alerts@test.com',
        ], $overrides);
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    #[Test]
    public function index_returns_only_authenticated_users_rules(): void
    {
        $user  = $this->user();
        $other = $this->user();

        Regla::factory()->count(3)->create(['user_id' => $user->id]);
        Regla::factory()->count(2)->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/rules');
        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    #[Test]
    public function index_includes_devices_for_each_rule(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);
        $rule   = Regla::factory()->create(['user_id' => $user->id]);
        $rule->dispositivos()->attach($device->id, ['alert_state' => 'ok']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/rules');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('0.devices'));
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/rules')->assertStatus(401);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    #[Test]
    public function store_creates_rule_with_associated_devices(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/rules', $this->rulePayload([$device->id]));

        $response->assertStatus(201)
            ->assertJsonFragment([
                'nombre'        => 'Regla de prueba',
                'operador'      => '>',
                'correo_activo' => true,
            ]);

        $this->assertDatabaseHas('reglas', [
            'nombre'  => 'Regla de prueba',
            'user_id' => $user->id,
        ]);

        $ruleId = $response->json('id');
        $this->assertDatabaseHas('dispositivo_regla', [
            'regla_id'       => $ruleId,
            'dispositivo_id' => $device->id,
        ]);
    }

    #[Test]
    public function store_sets_correct_notification_channels(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/rules', $this->rulePayload(
            [$device->id],
            ['methods' => ['telegram', 'discord'], 'recipient_email' => null]
        ));

        $response->assertStatus(201);
        $this->assertDatabaseHas('reglas', [
            'telegram_activo' => 1,
            'discord_activo'  => 1,
            'correo_activo'   => 0,
        ]);
    }

    #[Test]
    public function store_validates_operator_must_be_valid(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/rules', $this->rulePayload([$device->id], ['operator' => 'INVALID']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['operator']);
    }

    #[Test]
    public function store_validates_at_least_one_device_required(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/rules', $this->rulePayload([], ['devices' => []]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['devices']);
    }

    #[Test]
    public function store_validates_device_must_exist_in_database(): void
    {
        Sanctum::actingAs($this->user());

        $this->postJson('/api/rules', $this->rulePayload([99999]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['devices.0']);
    }

    #[Test]
    public function store_validates_for_duration_cannot_be_negative(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/rules', $this->rulePayload([$device->id], ['for_duration' => -1]))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['for_duration']);
    }

    #[Test]
    public function store_validates_name_is_required(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/rules', $this->rulePayload([$device->id], ['name' => '']))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    #[Test]
    public function update_modifies_own_rule(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);
        $rule   = Regla::factory()->create(['user_id' => $user->id, 'nombre' => 'Original']);
        $rule->dispositivos()->attach($device->id, ['alert_state' => 'ok']);

        Sanctum::actingAs($user);

        $this->putJson("/api/rules/{$rule->id}", $this->rulePayload([$device->id], [
            'name'     => 'Actualizada',
            'operator' => '<',
            'value'    => 50,
        ]))->assertStatus(200)->assertJsonFragment(['nombre' => 'Actualizada', 'operador' => '<']);

        $this->assertDatabaseHas('reglas', ['id' => $rule->id, 'nombre' => 'Actualizada']);
    }

    #[Test]
    public function update_returns_404_for_another_users_rule(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $rule  = Regla::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($other);

        $this->putJson("/api/rules/{$rule->id}", $this->rulePayload([]))
             ->assertStatus(404);
    }

    // ── Toggle ─────────────────────────────────────────────────────────────────

    #[Test]
    public function toggle_deactivates_active_rule(): void
    {
        $user = $this->user();
        $rule = Regla::factory()->create(['user_id' => $user->id, 'activo' => true]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/rules/{$rule->id}/toggle");
        $response->assertStatus(200)->assertJsonFragment(['activo' => false]);

        $this->assertDatabaseHas('reglas', ['id' => $rule->id, 'activo' => 0]);
    }

    #[Test]
    public function toggle_activates_inactive_rule(): void
    {
        $user = $this->user();
        $rule = Regla::factory()->inactive()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->patchJson("/api/rules/{$rule->id}/toggle");
        $response->assertStatus(200)->assertJsonFragment(['activo' => true]);
    }

    #[Test]
    public function toggle_returns_404_for_another_users_rule(): void
    {
        $rule = Regla::factory()->create(['user_id' => $this->user()->id]);

        Sanctum::actingAs($this->user());
        $this->patchJson("/api/rules/{$rule->id}/toggle")->assertStatus(404);
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    #[Test]
    public function destroy_deletes_own_rule(): void
    {
        $user = $this->user();
        $rule = Regla::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/rules/{$rule->id}")->assertStatus(200);
        $this->assertDatabaseMissing('reglas', ['id' => $rule->id]);
    }

    #[Test]
    public function destroy_returns_404_for_another_users_rule(): void
    {
        $rule = Regla::factory()->create(['user_id' => $this->user()->id]);

        Sanctum::actingAs($this->user());
        $this->deleteJson("/api/rules/{$rule->id}")->assertStatus(404);
    }

    #[Test]
    public function destroying_rule_also_removes_pivot_entries(): void
    {
        $user   = $this->user();
        $device = $this->deviceFor($user);
        $rule   = Regla::factory()->create(['user_id' => $user->id]);
        $rule->dispositivos()->attach($device->id, ['alert_state' => 'ok']);

        Sanctum::actingAs($user);
        $this->deleteJson("/api/rules/{$rule->id}")->assertStatus(200);

        $this->assertDatabaseMissing('dispositivo_regla', ['regla_id' => $rule->id]);
    }
}
