<?php

namespace Tests\Feature\Api;

use App\Models\RegistroAlerta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertApiTest extends TestCase
{
    use RefreshDatabase;

    private function authAs(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    // ── Autorización ───────────────────────────────────────────────────────────

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/alerts')->assertStatus(401);
    }

    #[Test]
    public function index_returns_only_current_users_alerts(): void
    {
        $user  = $this->authAs();
        $other = User::factory()->create();

        RegistroAlerta::factory()->count(4)->create(['user_id' => $user->id]);
        RegistroAlerta::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/alerts');
        $response->assertStatus(200);
        $this->assertSame(4, $response->json('total'));
    }

    // ── Paginación ─────────────────────────────────────────────────────────────

    #[Test]
    public function index_returns_paginated_results_with_default_20_per_page(): void
    {
        $user = $this->authAs();
        RegistroAlerta::factory()->count(25)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/alerts');
        $response->assertStatus(200);

        $this->assertSame(25, $response->json('total'));
        $this->assertCount(20, $response->json('data'));
        $this->assertSame(20, $response->json('per_page'));
    }

    #[Test]
    public function index_respects_per_page_param(): void
    {
        $user = $this->authAs();
        RegistroAlerta::factory()->count(10)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/alerts?per_page=5');
        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }

    #[Test]
    public function index_validates_per_page_maximum_is_100(): void
    {
        $this->authAs();
        $this->getJson('/api/alerts?per_page=101')->assertStatus(422);
    }

    // ── Filtro por dispositivo ─────────────────────────────────────────────────

    #[Test]
    public function filter_by_device_returns_only_matching_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->count(3)->create([
            'user_id'            => $user->id,
            'nombre_dispositivo' => 'Medidor A',
        ]);
        RegistroAlerta::factory()->count(2)->create([
            'user_id'            => $user->id,
            'nombre_dispositivo' => 'Medidor B',
        ]);

        $response = $this->getJson('/api/alerts?device=Medidor A');
        $response->assertStatus(200);
        $this->assertSame(3, $response->json('total'));
        foreach ($response->json('data') as $log) {
            $this->assertSame('Medidor A', $log['nombre_dispositivo']);
        }
    }

    // ── Filtro por regla ───────────────────────────────────────────────────────

    #[Test]
    public function filter_by_rule_returns_only_matching_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->count(2)->create([
            'user_id'      => $user->id,
            'nombre_regla' => 'Consumo alto',
        ]);
        RegistroAlerta::factory()->create([
            'user_id'      => $user->id,
            'nombre_regla' => 'Voltaje bajo',
        ]);

        $response = $this->getJson('/api/alerts?rule=Consumo alto');
        $this->assertSame(2, $response->json('total'));
    }

    // ── Filtro por tipo ────────────────────────────────────────────────────────

    #[Test]
    public function filter_by_type_firing_returns_only_firing_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->count(3)->firing()->create(['user_id' => $user->id]);
        RegistroAlerta::factory()->count(2)->resolution()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/alerts?type=firing');
        $this->assertSame(3, $response->json('total'));
        foreach ($response->json('data') as $log) {
            $this->assertSame('firing', $log['tipo']);
        }
    }

    #[Test]
    public function filter_by_type_resolution_returns_only_resolution_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->count(2)->firing()->create(['user_id' => $user->id]);
        RegistroAlerta::factory()->count(4)->resolution()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/alerts?type=resolution');
        $this->assertSame(4, $response->json('total'));
    }

    // ── Filtro por fecha ───────────────────────────────────────────────────────

    #[Test]
    public function filter_by_from_date_excludes_older_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(10),
        ]);
        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(2),
        ]);
        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now(),
        ]);

        $from     = now()->subDays(3)->format('Y-m-d');
        $response = $this->getJson("/api/alerts?from={$from}");
        $this->assertSame(2, $response->json('total'));
    }

    #[Test]
    public function filter_by_to_date_excludes_newer_logs(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now()->subDays(5),
        ]);
        RegistroAlerta::factory()->create([
            'user_id'    => $user->id,
            'created_at' => now(),
        ]);

        $to       = now()->subDays(3)->format('Y-m-d');
        $response = $this->getJson("/api/alerts?to={$to}");
        $this->assertSame(1, $response->json('total'));
    }

    #[Test]
    public function from_and_to_filters_can_be_combined(): void
    {
        $user = $this->authAs();

        RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(20)]);
        RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(7)]);
        RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(3)]);
        RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()]);

        $from = now()->subDays(8)->format('Y-m-d');
        $to   = now()->subDays(2)->format('Y-m-d');

        $response = $this->getJson("/api/alerts?from={$from}&to={$to}");
        $this->assertSame(2, $response->json('total'));
    }

    #[Test]
    public function from_date_must_be_valid_date_format(): void
    {
        $this->authAs();
        $this->getJson('/api/alerts?from=not-a-date')->assertStatus(422);
    }

    // ── Orden ──────────────────────────────────────────────────────────────────

    #[Test]
    public function results_are_ordered_by_created_at_descending(): void
    {
        $user = $this->authAs();

        $old  = RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(5)]);
        $new  = RegistroAlerta::factory()->create(['user_id' => $user->id, 'created_at' => now()]);

        $response = $this->getJson('/api/alerts');
        $data     = $response->json('data');

        $this->assertSame($new->id, $data[0]['id']);
        $this->assertSame($old->id, $data[1]['id']);
    }
}
