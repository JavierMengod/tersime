<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Services\InfluxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrediccionController extends Controller
{
    public function index()
    {
        $dispositivos = auth()->user()->dispositivos;
        return view('monitorizacion.prediccion', compact('dispositivos'));
    }

    public function obtenerDatos(Request $request, InfluxService $influx)
    {
        $inicio          = $request->query('start');
        $fin             = $request->query('stop');
        $etiqueta        = $request->query('device');
        $horasPrediccion = $request->query('predic_hours', Ajuste::get('predictor_default_hours', '24'));

        Log::info('[obtenerDatos] Entrada', ['start' => $inicio, 'stop' => $fin, 'device' => $etiqueta, 'predic_hours' => $horasPrediccion]);

        if (!$inicio || !$fin || !$etiqueta) {
            return response()->json(['error' => 'Faltan parámetros (start, stop, device)'], 400);
        }

        try {
            $fechaInicio = Carbon::parse($inicio)->startOfDay();
            $fechaFin    = Carbon::parse($fin)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Formato de fecha inválido'], 400);
        }

        try {
            $finFecha           = Carbon::parse($fin)->format('Y-m-d');
            $claveEntrenamiento = 'pred_training_' . $etiqueta . '_' . $finFecha;

            $datos = Cache::remember($claveEntrenamiento, 3600, function () use ($influx, $etiqueta, $fin) {
                return $influx->datosParaPrediccion($etiqueta, $fin);
            });

            $timestamps = $datos['timestamps'];
            $values     = $datos['values'];

            if (empty($timestamps)) {
                return response()->json(['error' => 'Sin datos en InfluxDB para este dispositivo'], 500);
            }

            Log::info('[obtenerDatos] Datos de entrenamiento', ['total' => count($timestamps)]);

            $urlPredictor = Ajuste::get('predictor_url');
            if (!$urlPredictor) {
                return response()->json(['error' => 'PREDICTOR_URL no configurado'], 500);
            }

            $clavePrediccion = 'pred_result_' . $etiqueta . '_' . $finFecha . '_' . $horasPrediccion;
            $predichos = Cache::remember($clavePrediccion, 1200, function () use (
                $urlPredictor, $timestamps, $values, $horasPrediccion
            ) {
                $timeout   = (int) (Ajuste::get('predictor_timeout') ?: 120);
                $respuesta = Http::timeout($timeout)->asJson()->post($urlPredictor, [
                    'timestamps'   => $timestamps,
                    'values'       => $values,
                    'predic_hours' => $horasPrediccion,
                ]);

                if ($respuesta->failed()) {
                    Log::error('[obtenerDatos] Error predictor', [
                        'status' => $respuesta->status(),
                        'body'   => $respuesta->body(),
                    ]);
                    return null;
                }

                $json            = $respuesta->json();
                $prediccionesRaw = $json['predichos'] ?? $json['predictions'] ?? $json['data'] ?? [];
                return $this->normalizarPredicciones($prediccionesRaw);
            });

            if ($predichos === null) {
                return response()->json(['error' => 'Error en el predictor'], 500);
            }

            $ahora   = Carbon::now('UTC');
            $predichos = array_filter($predichos, fn($p) => Carbon::parse($p['ds'])->greaterThan($ahora));

            $salida = [];
            foreach ($timestamps as $i => $t) {
                if (Carbon::parse($t)->between($fechaInicio, $fechaFin)) {
                    $salida[] = ['metric' => 'reales', 'time' => $t, 'value' => $values[$i]];
                }
            }
            foreach ($predichos as $p) {
                $salida[] = ['metric' => 'predichos', 'time' => $p['ds'], 'value' => $p['yhat']];
                if ($p['yhat_lower'] !== null) {
                    $salida[] = ['metric' => 'predichos_lower', 'time' => $p['ds'], 'value' => $p['yhat_lower']];
                    $salida[] = ['metric' => 'predichos_upper', 'time' => $p['ds'], 'value' => $p['yhat_upper']];
                }
            }

            Log::info('[obtenerDatos] Respuesta enviada', ['total' => count($salida)]);
            return response()->json($salida);

        } catch (\Throwable $e) {
            Log::error('[obtenerDatos] Excepción', [
                'msg'  => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            return response()->json(['error' => 'Error interno generando la predicción.'], 500);
        }
    }

    private function normalizarPredicciones(array $prediccionesRaw): array
    {
        $salida = [];

        foreach ($prediccionesRaw as $elemento) {
            if (!is_array($elemento)) continue;

            $ds    = $elemento['ds']         ?? $elemento['timestamp'] ?? $elemento[0] ?? null;
            $y     = $elemento['yhat']       ?? $elemento['value']     ?? $elemento[1] ?? null;
            $lower = $elemento['yhat_lower'] ?? $elemento[2] ?? null;
            $upper = $elemento['yhat_upper'] ?? $elemento[3] ?? null;

            if ($ds && is_numeric($y)) {
                $salida[] = [
                    'ds'         => (string) $ds,
                    'yhat'       => (float)  $y,
                    'yhat_lower' => $lower !== null ? (float) $lower : null,
                    'yhat_upper' => $upper !== null ? (float) $upper : null,
                ];
            }
        }

        return $salida;
    }
}
