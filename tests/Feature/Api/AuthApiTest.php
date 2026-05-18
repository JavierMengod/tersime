<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    // ── Login ──────────────────────────────────────────────────────────────────

    #[Test]
    public function user_can_login_with_valid_credentials_and_receives_token(): void
    {
        User::factory()->create([
            'name'     => 'operario1',
            'password' => bcrypt('secret123'),
            'enabled'  => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'name'     => 'operario1',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'language', 'timezone', 'theme', 'admin'],
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    #[Test]
    public function login_creates_personal_access_token_in_database(): void
    {
        $user = User::factory()->create(['password' => bcrypt('pass')]);

        $this->postJson('/api/auth/login', [
            'name'     => $user->name,
            'password' => 'pass',
        ])->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    #[Test]
    public function login_uses_device_name_for_token_when_provided(): void
    {
        $user = User::factory()->create(['password' => bcrypt('pass')]);

        $this->postJson('/api/auth/login', [
            'name'        => $user->name,
            'password'    => 'pass',
            'device_name' => 'mi-aplicacion',
        ])->assertStatus(200);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'mi-aplicacion',
        ]);
    }

    #[Test]
    public function login_returns_401_with_wrong_password(): void
    {
        User::factory()->create([
            'name'     => 'usuario',
            'password' => bcrypt('correcto'),
        ]);

        $this->postJson('/api/auth/login', [
            'name'     => 'usuario',
            'password' => 'incorrecto',
        ])->assertStatus(401)
          ->assertJsonFragment(['message' => 'Credenciales incorrectas.']);
    }

    #[Test]
    public function login_returns_401_for_nonexistent_user(): void
    {
        $this->postJson('/api/auth/login', [
            'name'     => 'no_existe',
            'password' => 'cualquier',
        ])->assertStatus(401);
    }

    #[Test]
    public function login_returns_403_for_disabled_user(): void
    {
        User::factory()->disabled()->create([
            'name'     => 'bloqueado',
            'password' => bcrypt('pass123'),
        ]);

        $this->postJson('/api/auth/login', [
            'name'     => 'bloqueado',
            'password' => 'pass123',
        ])->assertStatus(403)
          ->assertJsonFragment(['message' => 'Esta cuenta está deshabilitada.']);
    }

    #[Test]
    public function login_returns_422_when_name_is_missing(): void
    {
        $this->postJson('/api/auth/login', ['password' => 'pass'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function login_returns_422_when_password_is_missing(): void
    {
        $this->postJson('/api/auth/login', ['name' => 'user'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['password']);
    }

    // ── Me ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create(['admin' => true, 'language' => 'en']);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
             ->assertStatus(200)
             ->assertJson([
                 'id'       => $user->id,
                 'name'     => $user->name,
                 'language' => 'en',
                 'admin'    => true,
                 'enabled'  => true,
             ]);
    }

    #[Test]
    public function unauthenticated_request_to_me_returns_401(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    // ── Logout ─────────────────────────────────────────────────────────────────

    #[Test]
    public function logout_deletes_the_current_access_token(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-device');

        $this->withHeaders(['Authorization' => 'Bearer ' . $token->plainTextToken])
             ->postJson('/api/auth/logout')
             ->assertStatus(200)
             ->assertJsonFragment(['message' => 'Sesión cerrada.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function logout_only_deletes_current_token_not_others(): void
    {
        $user         = User::factory()->create();
        $activeToken  = $user->createToken('active');
        $user->createToken('other-device');

        $this->withHeaders(['Authorization' => 'Bearer ' . $activeToken->plainTextToken])
             ->postJson('/api/auth/logout')
             ->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'other-device']);
    }

    #[Test]
    public function unauthenticated_logout_returns_401(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }
}
