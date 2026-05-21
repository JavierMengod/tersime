<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InfluxService;
use App\Models\Ajuste;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrediccionController extends Controller
{
    /**
     * GET /api/prediction
     *
     * Devuelve series temporales (reales + predichos) para el panel de Grafana.
     * Formato de respuesta compatible con marcusolsson-json-datasource:
     *   [{metric, time, value}, ...]
     *
     * Parámetros:
     *   start        – fecha inicio del rango visible (ISO / Y-m-d)
     *   stop         – fecha fin del rango visible   (ISO / Y-m-d)
     *   device       – influx_tag del dispositivo
     *   predic_hours – horas a predecir (opcional, default: setting predictor_default_hours)
     */
    public function index(Request $request, InfluxService $influx)
    {
        $request->validate([
            'start'        => 'required|string',
            'stop'         => 'required|string',
            'device'       => 'required|string',
            'predic_hours' => 'sometimes|integer|min:1|max:720',
        ]);

        $start       = $request->query('start');
        $stop        = $request->query('stop');
        $device      = $request->query('device');
        $predicHours = (int) $request->query('predic_hours', Ajuste::get('predictor_default_hours', '24'));

        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $stopDate  = Carbon::parse($stop)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Formato de fecha inválido.'], 422);
        }

        $urlPredictor = Ajuste::get('predictor_url');
        if (!$urlPredictor) {
            return response()->json(['message' => 'Servicio de predicción no configurado.'], 503);
        }

        try {
            $stopDate0 = Carbon::parse($stop)->format('Y-m-d');
            $trainKey  = 'pred_training_' . $device . '_' . $stopDate0;
            $data      = Cache::remember($trainKey, 3600, fn () => $influx->datosParaPrediccion($device, $stop));

            if (empty($data['timestamps'])) {
                return response()->json(['message' => 'Sin datos históricos para este dispositivo.'], 422);
            }

            $predKey = 'pred_result_' . $device . '_' . $stopDate0 . '_' . $predicHours;
            $predRaw = Cache::remember($predKey, 1200, function () use (
                $urlPredictor, $data, $predicHours
            ) {
                $timeout = (int) (Ajuste::get('predictor_timeout') ?: 120);
                $resp    = Http::timeout($timeout)->asJson()->post($urlPredictor, [
                    'timestamps'   => $data['timestamps'],
                    'values'       => $data['values'],
                    'predic_hours' => $predicHours,
                ]);

                if ($resp->failed()) {
                    Log::error('[API] prediction predictor error', ['status' => $resp->status()]);
                    return null;
                }

                $json = $resp->json();
                return $json['predichos'] ?? $json['predictions'] ?? $json['data'] ?? [];
            });

            if ($predRaw === null) {
                return response()->json(['message' => 'Error en el servicio de predicción.'], 502);
            }

            $now      = Carbon::now('UTC');
            $output   = [];

            // Datos reales filtrados al rango visible
            foreach ($data['timestamps'] as $i => $t) {
                if (Carbon::parse($t)->between($startDate, $stopDate)) {
                    $output[] = ['metric' => 'reales', 'time' => $t, 'value' => $data['values'][$i]];
                }
            }

            // Predicciones futuras
            foreach ($predRaw as $item) {
                if (!is_array($item)) continue;

                $ds    = $item['ds']         ?? $item['timestamp'] ?? $item[0] ?? null;
                $y     = $item['yhat']       ?? $item['value']     ?? $item[1] ?? null;
                $lower = $item['yhat_lower'] ?? $item[2] ?? null;
                $upper = $item['yhat_upper'] ?? $item[3] ?? null;

                if (!$ds || !is_numeric($y)) continue;
                if (!Carbon::parse($ds)->greaterThan($now)) continue;

                $output[] = ['metric' => 'predichos',       'time' => (string) $ds, 'value' => (float) $y];

                if ($lower !== null) {
                    $output[] = ['metric' => 'predichos_lower', 'time' => (string) $ds, 'value' => (float) $lower];
                    $output[] = ['metric' => 'predichos_upper', 'time' => (string) $ds, 'value' => (float) $upper];
                }
            }

            return response()->json($output);

        } catch (\Throwable $e) {
            Log::error('[API] prediction exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error generando predicción.'], 500);
        }
    }
}
