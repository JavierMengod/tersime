<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GrafanaProxyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('grafana_base_url', 'http://grafana-test:3000');

        $this->user = User::factory()->create([
            'email' => 'test@tersime.com',
        ]);
    }

    // ── Autenticación ──────────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_request_redirects_to_login(): void
    {
        $this->get('/grafana/d/dashboard-id/panel')->assertRedirect(route('login'));
    }

    /** @test */
    public function unauthenticated_api_request_redirects_to_login(): void
    {
        $this->get('/grafana/api/dashboards/uid/abc123')->assertRedirect(route('login'));
    }

    // ── Cabecera X-WEBAUTH-USER ────────────────────────────────────────────────

    /** @test */
    public function proxy_injects_x_webauth_user_header_with_user_email(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->actingAs($this->user)->get('/grafana/d/panel');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-WEBAUTH-USER', $this->user->email);
        });
    }

    /** @test */
    public function proxy_uses_authenticated_users_email_not_a_generic_value(): void
    {
        $other = User::factory()->create(['email' => 'other@tersime.com']);

        Http::fake([
            'http://grafana-test:3000/*' => Http::response('ok', 200),
        ]);

        $this->actingAs($other)->get('/grafana/public/fonts/roboto.woff');

        Http::assertSent(function ($request) use ($other) {
            return $request->hasHeader('X-WEBAUTH-USER', $other->email);
        });
    }

    // ── Respuesta del proxy ────────────────────────────────────────────────────

    /** @test */
    public function proxy_returns_grafana_response_body_and_status(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('grafana dashboard html', 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/d/dashboard');

        $response->assertStatus(200);
        $this->assertStringContainsString('grafana dashboard html', $response->getContent());
    }

    /** @test */
    public function proxy_forwards_non_200_status_codes_from_grafana(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('Not Found', 404),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/nonexistent');

        $response->assertStatus(404);
    }

    /** @test */
    public function proxy_returns_502_when_grafana_is_unreachable(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => function () {
                throw new \Exception('Connection refused');
            },
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/d/dashboard');

        $response->assertStatus(502);
        $this->assertStringContainsString('Proxy error', $response->getContent());
    }

    // ── Cabeceras de seguridad ─────────────────────────────────────────────────

    /** @test */
    public function proxy_strips_x_frame_options_from_grafana_response(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('ok', 200, [
                'Content-Type'    => 'text/html',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/d/panel');

        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    /** @test */
    public function proxy_strips_content_security_policy_from_grafana_response(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('ok', 200, [
                'Content-Type'            => 'text/html',
                'Content-Security-Policy' => "frame-ancestors 'self'",
            ]),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/d/panel');

        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    /** @test */
    public function proxy_passes_through_cache_control_header(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('js bundle', 200, [
                'Content-Type'  => 'application/javascript',
                'Cache-Control' => 'public, max-age=31536000',
            ]),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/public/build/app.js');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('max-age=31536000', $cacheControl);
        $this->assertStringContainsString('public', $cacheControl);
    }

    // ── Rutas y métodos ────────────────────────────────────────────────────────

    /** @test */
    public function proxy_forwards_query_string_to_grafana(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('ok', 200),
        ]);

        $this->actingAs($this->user)->get('/grafana/d/panel?orgId=1&refresh=5s');

        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'orgId=1') &&
                   str_contains((string) $request->url(), 'refresh=5s');
        });
    }

    /** @test */
    public function proxy_constructs_upstream_url_from_setting(): void
    {
        Setting::set('grafana_base_url', 'http://custom-grafana:4000/grafana');

        Http::fake([
            'http://custom-grafana:4000/*' => Http::response('ok', 200),
        ]);

        $this->actingAs($this->user)->get('/grafana/api/health');

        Http::assertSent(function ($request) {
            return str_starts_with((string) $request->url(), 'http://custom-grafana:4000/grafana/api/health');
        });
    }

    /** @test */
    public function proxy_supports_post_method_for_api_queries(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('{"results":{}}', 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->actingAs($this->user)
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->postJson('/grafana/api/ds/query', ['queries' => []]);

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST';
        });
    }

    /** @test */
    public function proxy_injects_x_webauth_user_even_on_post_requests(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('{}', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->actingAs($this->user)
            ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
            ->postJson('/grafana/api/ds/query', []);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-WEBAUTH-USER', $this->user->email);
        });
    }

    // ── Content-Type ───────────────────────────────────────────────────────────

    /** @test */
    public function proxy_preserves_json_content_type_from_grafana(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('{"status":"ok"}', 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/api/health');

        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function proxy_preserves_javascript_content_type_from_grafana(): void
    {
        Http::fake([
            'http://grafana-test:3000/*' => Http::response('var x = 1;', 200, ['Content-Type' => 'application/javascript']),
        ]);

        $response = $this->actingAs($this->user)->get('/grafana/public/build/app.js');

        $this->assertStringContainsString('javascript', $response->headers->get('Content-Type'));
    }
}
