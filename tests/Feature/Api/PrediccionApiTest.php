<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\InfluxController;
use App\Models\Dispositivo;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrediccionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Dispositivo $dispositivo;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('predictor_url', 'http://predictor:5000/predict');
        Setting::set('predictor_timeout', '30');
        Setting::set('predictor_default_hours', '24');

        $this->user        = User::factory()->create();
        $this->dispositivo = Dispositivo::factory()->create(['influx_tag' => 'DEV-001']);
        $this->user->dispositivos()->attach($this->dispositivo->id, ['habilitado' => 1]);
    }

    // ── Autenticación ──────────────────────────────────────────────────────────

    /** @test */
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(401);
    }

    // ── Validación de parámetros ───────────────────────────────────────────────

    /** @test */
    public function missing_start_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/prediction?stop=2024-01-31&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonValidationErrors('start');
    }

    /** @test */
    public function missing_stop_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/prediction?start=2024-01-01&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonValidationErrors('stop');
    }

    /** @test */
    public function missing_device_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31')
             ->assertStatus(422)
             ->assertJsonValidationErrors('device');
    }

    // ── Configuración ──────────────────────────────────────────────────────────

    /** @test */
    public function returns_503_when_predictor_url_not_configured(): void
    {
        Setting::set('predictor_url', '');
        Sanctum::actingAs($this->user);

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-01T00:00:00Z'], 'values' => [1.0]]);
        });

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(503)
             ->assertJsonFragment(['message' => 'Servicio de predicción no configurado.']);
    }

    /** @test */
    public function returns_422_when_no_historical_data(): void
    {
        Sanctum::actingAs($this->user);

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => [], 'values' => []]);
        });

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Sin datos históricos para este dispositivo.']);
    }

    /** @test */
    public function returns_502_when_predictor_service_fails(): void
    {
        Sanctum::actingAs($this->user);

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-01T00:00:00Z'], 'values' => [1.0]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response('error', 500)]);

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(502)
             ->assertJsonFragment(['message' => 'Error en el servicio de predicción.']);
    }

    // ── Formato de respuesta ───────────────────────────────────────────────────

    /** @test */
    public function returns_json_array_with_metric_time_value_keys(): void
    {
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn([
                  'timestamps' => ['2024-01-10T00:00:00Z'],
                  'values'     => [2.5],
              ]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 3.1, 'yhat_lower' => 2.8, 'yhat_upper' => 3.4],
            ],
        ], 200)]);

        $response = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('metric', $data[0]);
        $this->assertArrayHasKey('time',   $data[0]);
        $this->assertArrayHasKey('value',  $data[0]);

        Carbon::setTestNow();
    }

    /** @test */
    public function real_data_is_filtered_to_start_stop_range(): void
    {
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2024-01-15 12:00:00');

        // timestamps: one inside range, one outside
        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn([
                  'timestamps' => ['2024-01-10T00:00:00Z', '2023-12-01T00:00:00Z'],
                  'values'     => [2.5, 9.9],
              ]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response(['predichos' => []], 200)]);

        $response = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');

        $reales = array_filter($response->json(), fn($r) => $r['metric'] === 'reales');

        $this->assertCount(1, $reales);
        $this->assertSame('2024-01-10T00:00:00Z', array_values($reales)[0]['time']);

        Carbon::setTestNow();
    }

    /** @test */
    public function past_predictions_are_excluded(): void
    {
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-14T00:00:00Z', 'yhat' => 1.0, 'yhat_lower' => null, 'yhat_upper' => null], // past
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 2.0, 'yhat_lower' => null, 'yhat_upper' => null], // future
            ],
        ], 200)]);

        $response  = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');
        $predichos = array_filter($response->json(), fn($r) => $r['metric'] === 'predichos');

        $this->assertCount(1, $predichos);
        $this->assertSame('2024-01-16T00:00:00Z', array_values($predichos)[0]['time']);

        Carbon::setTestNow();
    }

    /** @test */
    public function predictions_with_bounds_produce_three_series(): void
    {
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 3.0, 'yhat_lower' => 2.5, 'yhat_upper' => 3.5],
            ],
        ], 200)]);

        $response = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');
        $data     = $response->json();

        $metrics = array_column($data, 'metric');
        $this->assertContains('predichos',       $metrics);
        $this->assertContains('predichos_lower', $metrics);
        $this->assertContains('predichos_upper', $metrics);

        Carbon::setTestNow();
    }

    /** @test */
    public function predictions_without_bounds_produce_only_main_series(): void
    {
        Sanctum::actingAs($this->user);

        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxController::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 3.0, 'yhat_lower' => null, 'yhat_upper' => null],
            ],
        ], 200)]);

        $response = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');
        $metrics  = array_column($response->json(), 'metric');

        $this->assertContains('predichos', $metrics);
        $this->assertNotContains('predichos_lower', $metrics);
        $this->assertNotContains('predichos_upper', $metrics);

        Carbon::setTestNow();
    }
}
