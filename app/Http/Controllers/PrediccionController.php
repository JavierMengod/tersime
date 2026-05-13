<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrediccionController extends Controller
{
    public function index()
    {
        Log::info('📡 Entrando en PrediccionController@index');
        $dispositivos = auth()->user()->dispositivos;
        return view('monitorizacion.prediccion', compact('dispositivos'));
    }

    public function obtenerDatos(Request $request)
    {
        $start = $request->query('start');
        $stop = $request->query('stop');
        $device = $request->query('device');
        $predic_hours = $request->query('predic_hours', 24);

        Log::info('🔹 [obtenerDatos] Entrada', compact('start', 'stop', 'device', 'predic_hours'));

        if (!$start || !$stop || !$device) {
            return response()->json(['error' => 'Faltan parámetros (start, stop, device)'], 400);
        }

        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $stopDate = Carbon::parse($stop)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Formato de fecha inválido'], 400);
        }

        try {
            $grafanaUrl = config('app.grafana_api_ds_query');
            $grafanaToken = env('GRAFANA_API_KEY');
            $timeout = 90;

            // --- 1️⃣ Iterar por meses (sin cache, sin solapamientos) ---
            #$cursor = $startDate->copy()->startOfMonth();
            $map = [];
            $cursor = $stopDate->copy()->subYears(1)->startOfMonth();

            // Ajuste: si el usuario pide menos de dos años, aun así se mandan los últimos dos
            Log::info('🔹 [obtenerDatos] Solicitando histórico de los últimos 2 años', [
                'desde' => $cursor->toDateString(),
                'hasta' => $stopDate->toDateString()
            ]);

            while ($cursor->lessThanOrEqualTo($stopDate)) {
                $chunkStart = $cursor->copy()->startOfMonth();
                $chunkEnd = $cursor->copy()->endOfMonth();

                if ($chunkEnd->greaterThan($stopDate)) {
                    $chunkEnd = $stopDate->copy();
                }

                $s = $chunkStart->format('Y-m-d');
                $e = $chunkEnd->format('Y-m-d');

                Log::info("🔸 [obtenerDatos] Pidiendo chunk a Grafana", ['desde' => $s, 'hasta' => $e]);

                $fluxQuery = <<<FLUX
from(bucket: "PINZAS")
  |> range(start: {$s}T00:00:00Z, stop: {$e}T23:59:59Z)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> sort(columns:["_time"])
FLUX;

                $body = [
                    "queries" => [
                        [
                            "refId" => "A",
                            "datasourceId" => 3,
                            "query" => $fluxQuery,
                            "format" => "table"
                        ]
                    ],
                    "from" => "{$s}T00:00:00Z",
                    "to" => "{$e}T23:59:59Z"
                ];

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$grafanaToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])->timeout($timeout)->post($grafanaUrl, $body);

                if ($response->failed()) {
                    Log::error('❌ [obtenerDatos] Error Grafana', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    break;
                }

                $json = $response->json();
                [$timestamps, $values] = $this->extractTimestampsAndValuesFromGrafana($json);

                if (empty($timestamps)) {
                    Log::warning('⚠️ [obtenerDatos] Grafana devolvió bloque vacío', ['desde' => $s, 'hasta' => $e]);
                } else {
                    $len = min(count($timestamps), count($values));
                    for ($i = 0; $i < $len; $i++) {
                        $ts = $this->convertToIsoUtc($timestamps[$i]);
                        if ($ts)
                            $map[$ts] = (float) $values[$i];
                    }
                    Log::info('✅ [obtenerDatos] Chunk procesado', [
                        'desde' => $s,
                        'hasta' => $e,
                        'puntos_totales' => count($map)
                    ]);
                }

                // Avanza un mes
                $cursor->addMonth();
            }

            if (empty($map)) {
                return response()->json(['error' => 'Grafana no devolvió datos válidos'], 500);
            }

            ksort($map);
            $timestamps = array_keys($map);
            $values = array_values($map);

            Log::info('🔹 [obtenerDatos] Total datos obtenidos', ['total' => count($timestamps)]);

            // --- 2️⃣ Predictor ---
            $urlPredictor = env('PREDICTOR_URL');
            if (!$urlPredictor) {
                return response()->json(['error' => 'PREDICTOR_URL no configurado'], 500);
            }

            $payload = [
                'timestamps' => $timestamps,
                'values' => $values,
                'predic_hours' => $predic_hours
            ];

            $predResponse = Http::timeout(120)->asJson()->post($urlPredictor, $payload);

            if ($predResponse->failed()) {
                Log::error('❌ [obtenerDatos] Error predictor', [
                    'status' => $predResponse->status(),
                    'body' => $predResponse->body()
                ]);
                return response()->json(['error' => 'Error en el predictor'], 500);
            }

            $jsonPred = $predResponse->json();
            $pred_raw = $jsonPred['predichos'] ?? $jsonPred['predictions'] ?? $jsonPred['data'] ?? [];
            $predichos = $this->normalizePredictions($pred_raw);

            // --- 3️⃣ Filtrar solo futuras ---
            $now = Carbon::now('UTC');
            $predichos = array_filter($predichos, fn($p) => Carbon::parse($p['ds'])->greaterThan($now));

            // --- 4️⃣ Resultado Grafana ---
            $output = [];
            foreach ($timestamps as $i => $t) {
                $fecha = Carbon::parse($t);
                if ($fecha->between($startDate, $stopDate)) {
                    $output[] = ['metric' => 'reales', 'time' => $t, 'value' => $values[$i]];
                }
            }

            foreach ($predichos as $p) {
                $output[] = ['metric' => 'predichos', 'time' => $p['ds'], 'value' => $p['yhat']];
            }

            Log::info('✅ [obtenerDatos] Datos finales enviados a Grafana', ['total' => count($output)]);
            return response()->json($output);

        } catch (\Throwable $e) {
            Log::error('💥 [obtenerDatos] Excepción', [
                'msg' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            return response()->json(['error' => 'Excepción: ' . $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------
    // AUXILIARES
    // -------------------------------------------------------------
    private function normalizePredictions($pred_raw)
    {
        $out = [];
        if (!is_array($pred_raw))
            return $out;

        foreach ($pred_raw as $item) {
            if (is_array($item)) {
                $ds = $item['ds'] ?? $item['timestamp'] ?? $item[0] ?? null;
                $y = $item['yhat'] ?? $item['value'] ?? $item[1] ?? null;
                if ($ds && is_numeric($y)) {
                    $out[] = ['ds' => (string) $ds, 'yhat' => (float) $y];
                }
            }
        }
        return $out;
    }

    private function extractTimestampsAndValuesFromGrafana($json)
    {
        $timestamps = [];
        $values = [];

        if (isset($json['results'])) {
            foreach ($json['results'] as $res) {
                if (isset($res['frames'])) {
                    foreach ($res['frames'] as $frame) {
                        if (isset($frame['data']['values'][0], $frame['data']['values'][1])) {
                            $t = $frame['data']['values'][0];
                            $v = $frame['data']['values'][1];
                            $len = min(count($t), count($v));
                            for ($i = 0; $i < $len; $i++) {
                                $timestamps[] = $t[$i];
                                $values[] = $v[$i];
                            }
                        }
                    }
                }
            }
        }

        return [$timestamps, $values];
    }

    private function convertToIsoUtc($t)
    {
        if (!is_numeric($t)) {
            try {
                $dt = new \DateTime($t);
                $dt->setTimezone(new \DateTimeZone('UTC'));
                return $dt->format('Y-m-d\TH:i:s\Z');
            } catch (\Exception $e) {
                return null;
            }
        }

        $num = (float) $t;
        $sec = ($num > 1e12) ? intval($num / 1000) : intval($num);
        return gmdate('Y-m-d\TH:i:s\Z', $sec);
    }

    // -------------------------------------------------------------
    // TOKENS
    // -------------------------------------------------------------
    public function store(Request $request)
    {
        $request->validate(['nombre' => 'required|string|max:255']);
        $token = auth()->user()->createToken($request->nombre)->plainTextToken;
        return redirect()->back()->with('token_creado', $token);
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $user->tokens()->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Token eliminado correctamente.');
    }
}
