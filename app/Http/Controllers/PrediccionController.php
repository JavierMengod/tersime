<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function obtenerDatos(Request $request, InfluxController $influx)
    {
        $start        = $request->query('start');
        $stop         = $request->query('stop');
        $device       = $request->query('device');
        $predic_hours = $request->query('predic_hours', 24);

        Log::info('🔹 [obtenerDatos] Entrada', compact('start', 'stop', 'device', 'predic_hours'));

        if (!$start || !$stop || !$device) {
            return response()->json(['error' => 'Faltan parámetros (start, stop, device)'], 400);
        }

        try {
            $startDate = Carbon::parse($start)->startOfDay();
            $stopDate  = Carbon::parse($stop)->endOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Formato de fecha inválido'], 400);
        }

        try {
            // --- 1. Datos históricos directos de InfluxDB, cacheados 1 hora ---
            $cacheKey = 'pred_training_' . $device . '_' . Carbon::parse($stop)->format('Y-m-d');

            $data = Cache::remember($cacheKey, 3600, function () use ($influx, $device, $stop) {
                return $influx->datosParaPrediccion($device, $stop);
            });

            $timestamps = $data['timestamps'];
            $values     = $data['values'];

            if (empty($timestamps)) {
                return response()->json(['error' => 'Sin datos en InfluxDB para este dispositivo'], 500);
            }

            Log::info('🔹 [obtenerDatos] Datos de entrenamiento', ['total' => count($timestamps)]);

            // --- 2. Predictor ---
            $urlPredictor = env('PREDICTOR_URL');
            if (!$urlPredictor) {
                return response()->json(['error' => 'PREDICTOR_URL no configurado'], 500);
            }

            $predResponse = Http::timeout(120)->asJson()->post($urlPredictor, [
                'timestamps'   => $timestamps,
                'values'       => $values,
                'predic_hours' => $predic_hours,
            ]);

            if ($predResponse->failed()) {
                Log::error('❌ [obtenerDatos] Error predictor', [
                    'status' => $predResponse->status(),
                    'body'   => $predResponse->body(),
                ]);
                return response()->json(['error' => 'Error en el predictor'], 500);
            }

            $jsonPred  = $predResponse->json();
            $pred_raw  = $jsonPred['predichos'] ?? $jsonPred['predictions'] ?? $jsonPred['data'] ?? [];
            $predichos = $this->normalizePredictions($pred_raw);

            // --- 3. Solo predicciones futuras ---
            $now       = Carbon::now('UTC');
            $predichos = array_filter($predichos, fn($p) => Carbon::parse($p['ds'])->greaterThan($now));

            // --- 4. Reales filtrados al rango visible + predicciones ---
            $output = [];
            foreach ($timestamps as $i => $t) {
                if (Carbon::parse($t)->between($startDate, $stopDate)) {
                    $output[] = ['metric' => 'reales', 'time' => $t, 'value' => $values[$i]];
                }
            }
            foreach ($predichos as $p) {
                $output[] = ['metric' => 'predichos', 'time' => $p['ds'], 'value' => $p['yhat']];
            }

            Log::info('✅ [obtenerDatos] Respuesta enviada', ['total' => count($output)]);
            return response()->json($output);

        } catch (\Throwable $e) {
            Log::error('💥 [obtenerDatos] Excepción', [
                'msg'  => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
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
