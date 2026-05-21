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
     *   device       – etiqueta_influx del dispositivo
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

        $inicio          = $request->query('start');
        $fin             = $request->query('stop');
        $etiqueta        = $request->query('device');
        $horasPrediccion = (int) $request->query('predic_hours', Ajuste::get('predictor_default_hours', '24'));

        try {
            $fechaInicio = Carbon::parse($inicio)->startOfDay();
            $fechaFin    = Carbon::parse($fin)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Formato de fecha inválido.'], 422);
        }

        $urlPredictor = Ajuste::get('predictor_url');
        if (!$urlPredictor) {
            return response()->json(['message' => 'Servicio de predicción no configurado.'], 503);
        }

        try {
            $finFecha           = Carbon::parse($fin)->format('Y-m-d');
            $claveEntrenamiento = 'pred_training_' . $etiqueta . '_' . $finFecha;
            $datos              = Cache::remember($claveEntrenamiento, 3600, fn () => $influx->datosParaPrediccion($etiqueta, $fin));

            if (empty($datos['timestamps'])) {
                return response()->json(['message' => 'Sin datos históricos para este dispositivo.'], 422);
            }

            $clavePrediccion = 'pred_result_' . $etiqueta . '_' . $finFecha . '_' . $horasPrediccion;
            $prediccionesRaw = Cache::remember($clavePrediccion, 1200, function () use (
                $urlPredictor, $datos, $horasPrediccion
            ) {
                $timeout   = (int) (Ajuste::get('predictor_timeout') ?: 120);
                $respuesta = Http::timeout($timeout)->asJson()->post($urlPredictor, [
                    'timestamps'   => $datos['timestamps'],
                    'values'       => $datos['values'],
                    'predic_hours' => $horasPrediccion,
                ]);

                if ($respuesta->failed()) {
                    Log::error('[API] prediction predictor error', ['status' => $respuesta->status()]);
                    return null;
                }

                $json = $respuesta->json();
                return $json['predichos'] ?? $json['predictions'] ?? $json['data'] ?? [];
            });

            if ($prediccionesRaw === null) {
                return response()->json(['message' => 'Error en el servicio de predicción.'], 502);
            }

            $ahora = Carbon::now('UTC');
            $salida = [];

            foreach ($datos['timestamps'] as $i => $t) {
                if (Carbon::parse($t)->between($fechaInicio, $fechaFin)) {
                    $salida[] = ['metric' => 'reales', 'time' => $t, 'value' => $datos['values'][$i]];
                }
            }

            foreach ($prediccionesRaw as $elemento) {
                if (!is_array($elemento)) continue;

                $ds    = $elemento['ds']         ?? $elemento['timestamp'] ?? $elemento[0] ?? null;
                $y     = $elemento['yhat']       ?? $elemento['value']     ?? $elemento[1] ?? null;
                $lower = $elemento['yhat_lower'] ?? $elemento[2] ?? null;
                $upper = $elemento['yhat_upper'] ?? $elemento[3] ?? null;

                if (!$ds || !is_numeric($y)) continue;
                if (!Carbon::parse($ds)->greaterThan($ahora)) continue;

                $salida[] = ['metric' => 'predichos',       'time' => (string) $ds, 'value' => (float) $y];

                if ($lower !== null) {
                    $salida[] = ['metric' => 'predichos_lower', 'time' => (string) $ds, 'value' => (float) $lower];
                    $salida[] = ['metric' => 'predichos_upper', 'time' => (string) $ds, 'value' => (float) $upper];
                }
            }

            return response()->json($salida);

        } catch (\Throwable $e) {
            Log::error('[API] prediction exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error generando predicción.'], 500);
        }
    }
}
