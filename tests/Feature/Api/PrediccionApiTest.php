<?php

namespace Tests\Feature\Api;

use App\Services\InfluxService;
use App\Models\Dispositivo;
use App\Models\Ajuste;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrediccionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Ajuste::set('predictor_url', 'http://predictor:5000/predict');
        Ajuste::set('predictor_timeout', '30');
        Ajuste::set('predictor_default_hours', '24');
    }

    // ── Acceso público ─────────────────────────────────────────────────────────

    #[Test]
    public function endpoint_is_publicly_accessible_without_token(): void
    {
        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => [], 'values' => []]);
        });

        // Sin autenticación → no 401 sino respuesta de negocio (422 sin datos)
        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(422);
    }

    // ── Validación de parámetros ───────────────────────────────────────────────

    #[Test]
    public function missing_start_returns_422(): void
    {
        $this->getJson('/api/prediction?stop=2024-01-31&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonValidationErrors('start');
    }

    #[Test]
    public function missing_stop_returns_422(): void
    {
        $this->getJson('/api/prediction?start=2024-01-01&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonValidationErrors('stop');
    }

    #[Test]
    public function missing_device_returns_422(): void
    {
        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31')
             ->assertStatus(422)
             ->assertJsonValidationErrors('device');
    }

    // ── Configuración ──────────────────────────────────────────────────────────

    #[Test]
    public function returns_503_when_predictor_url_not_configured(): void
    {
        Ajuste::set('predictor_url', '');

        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-01T00:00:00Z'], 'values' => [1.0]]);
        });

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(503)
             ->assertJsonFragment(['message' => 'Servicio de predicción no configurado.']);
    }

    #[Test]
    public function returns_422_when_no_historical_data(): void
    {
        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => [], 'values' => []]);
        });

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(422)
             ->assertJsonFragment(['message' => 'Sin datos históricos para este dispositivo.']);
    }

    #[Test]
    public function returns_502_when_predictor_service_fails(): void
    {
        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-01T00:00:00Z'], 'values' => [1.0]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response('error', 500)]);

        $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')
             ->assertStatus(502)
             ->assertJsonFragment(['message' => 'Error en el servicio de predicción.']);
    }

    // ── Formato de respuesta ───────────────────────────────────────────────────

    #[Test]
    public function returns_json_array_with_metric_time_value_keys(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxService::class, function ($m) {
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
        $item = $response->json()[0];
        $this->assertArrayHasKey('metric', $item);
        $this->assertArrayHasKey('time',   $item);
        $this->assertArrayHasKey('value',  $item);

        Carbon::setTestNow();
    }

    #[Test]
    public function real_data_is_filtered_to_start_stop_range(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn([
                  'timestamps' => ['2024-01-10T00:00:00Z', '2023-12-01T00:00:00Z'],
                  'values'     => [2.5, 9.9],
              ]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response(['predichos' => []], 200)]);

        $response = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');

        $reales = array_values(array_filter($response->json(), fn($r) => $r['metric'] === 'reales'));

        $this->assertCount(1, $reales);
        $this->assertSame('2024-01-10T00:00:00Z', $reales[0]['time']);

        Carbon::setTestNow();
    }

    #[Test]
    public function past_predictions_are_excluded(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-14T00:00:00Z', 'yhat' => 1.0, 'yhat_lower' => null, 'yhat_upper' => null],
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 2.0, 'yhat_lower' => null, 'yhat_upper' => null],
            ],
        ], 200)]);

        $response  = $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001');
        $predichos = array_values(array_filter($response->json(), fn($r) => $r['metric'] === 'predichos'));

        $this->assertCount(1, $predichos);
        $this->assertSame('2024-01-16T00:00:00Z', $predichos[0]['time']);

        Carbon::setTestNow();
    }

    #[Test]
    public function predictions_with_bounds_produce_three_series(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 3.0, 'yhat_lower' => 2.5, 'yhat_upper' => 3.5],
            ],
        ], 200)]);

        $metrics = array_column(
            $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')->json(),
            'metric'
        );

        $this->assertContains('predichos',       $metrics);
        $this->assertContains('predichos_lower', $metrics);
        $this->assertContains('predichos_upper', $metrics);

        Carbon::setTestNow();
    }

    #[Test]
    public function predictions_without_bounds_produce_only_main_series(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        $this->mock(InfluxService::class, function ($m) {
            $m->shouldReceive('datosParaPrediccion')
              ->andReturn(['timestamps' => ['2024-01-10T00:00:00Z'], 'values' => [2.5]]);
        });

        Http::fake(['http://predictor:5000/predict' => Http::response([
            'predichos' => [
                ['ds' => '2024-01-16T00:00:00Z', 'yhat' => 3.0, 'yhat_lower' => null, 'yhat_upper' => null],
            ],
        ], 200)]);

        $metrics = array_column(
            $this->getJson('/api/prediction?start=2024-01-01&stop=2024-01-31&device=DEV-001')->json(),
            'metric'
        );

        $this->assertContains('predichos', $metrics);
        $this->assertNotContains('predichos_lower', $metrics);
        $this->assertNotContains('predichos_upper', $metrics);

        Carbon::setTestNow();
    }
}
