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

class DeviceController extends Controller
{
    protected InfluxService $influx;

    public function __construct(InfluxService $influx)
    {
        $this->influx = $influx;
    }

    public function index(Request $request)
    {
        $dispositivos = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1)
            ->get()
            ->map(function ($d) {
                return [
                    'id'              => $d->id,
                    'etiqueta_influx' => $d->etiqueta_influx,
                    'nombre'          => $d->nombre,
                ];
            });

        return response()->json($dispositivos);
    }

    public function current(Request $request, $id)
    {
        $dispositivo = $this->buscarDispositivo($request, $id);

        if (!$dispositivo) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $valor = $this->influx->ultimoValor($dispositivo->etiqueta_influx);

        return response()->json([
            'device'    => $dispositivo->etiqueta_influx,
            'nombre'    => $dispositivo->nombre,
            'value_kwh' => $valor,
            'has_data'  => $valor !== null,
        ]);
    }

    public function consumption(Request $request, $id)
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $dispositivo = $this->buscarDispositivo($request, $id);

        if (!$dispositivo) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $desde   = $request->input('from');
        $hasta   = $request->input('to');
        $etiqueta = $dispositivo->etiqueta_influx;

        $total    = $this->influx->consumoTotal($etiqueta, $desde, $hasta);
        $horarios = $this->influx->datosHorarios($etiqueta, $desde, $hasta);
        $diarios  = $this->influx->datosDiarios($etiqueta, $desde, $hasta);

        return response()->json([
            'device'    => $etiqueta,
            'nombre'    => $dispositivo->nombre,
            'from'      => $desde,
            'to'        => $hasta,
            'total_kwh' => round($total, 4),
            'hourly'    => $horarios,
            'daily'     => $diarios,
        ]);
    }

    public function stats(Request $request, $id)
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $dispositivo = $this->buscarDispositivo($request, $id);

        if (!$dispositivo) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $desde    = $request->input('from');
        $hasta    = $request->input('to');
        $etiqueta = $dispositivo->etiqueta_influx;

        $estadisticas = $this->influx->datosEstadisticos($etiqueta, $desde, $hasta);
        $factorCarga  = $this->influx->factorCarga($etiqueta, $desde, $hasta);

        return response()->json([
            'device'      => $etiqueta,
            'nombre'      => $dispositivo->nombre,
            'from'        => $desde,
            'to'          => $hasta,
            'mean_kwh'    => $estadisticas['mean']   !== null ? round($estadisticas['mean'],   4) : null,
            'stddev_kwh'  => $estadisticas['stddev'] !== null ? round($estadisticas['stddev'], 4) : null,
            'max_kwh'     => $estadisticas['max']    !== null ? round($estadisticas['max'],    4) : null,
            'min_kwh'     => $estadisticas['min']    !== null ? round($estadisticas['min'],    4) : null,
            'total_kwh'   => $estadisticas['sum']    !== null ? round($estadisticas['sum'],    4) : null,
            'load_factor' => $factorCarga             !== null ? round($factorCarga,            4) : null,
        ]);
    }

    public function forecast(Request $request, $id)
    {
        $request->validate([
            'hours' => 'sometimes|integer|min:1|max:168',
        ]);

        $dispositivo = $this->buscarDispositivo($request, $id);

        if (!$dispositivo) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $etiqueta     = $dispositivo->etiqueta_influx;
        $horas        = (int) $request->input('hours', 24);
        $fin          = Carbon::now()->format('Y-m-d');

        $urlPredictor = Ajuste::get('predictor_url');
        if (!$urlPredictor) {
            return response()->json(['message' => 'Servicio de predicción no configurado.'], 503);
        }

        try {
            $claveCache = 'pred_training_' . $etiqueta . '_' . $fin;
            $datos = Cache::remember($claveCache, 3600, function () use ($etiqueta, $fin) {
                return $this->influx->datosParaPrediccion($etiqueta, $fin);
            });

            if (empty($datos['timestamps'])) {
                return response()->json(['message' => 'Sin datos históricos para este dispositivo.'], 422);
            }

            $respuestaPredictor = Http::timeout(120)->asJson()->post($urlPredictor, [
                'timestamps'   => $datos['timestamps'],
                'values'       => $datos['values'],
                'predic_hours' => $horas,
            ]);

            if ($respuestaPredictor->failed()) {
                Log::error('[API] forecast predictor error', ['status' => $respuestaPredictor->status()]);
                return response()->json(['message' => 'Error en el servicio de predicción.'], 502);
            }

            $json            = $respuestaPredictor->json();
            $prediccionesRaw = $json['predichos'] ?? $json['predictions'] ?? $json['data'] ?? [];
            $ahora           = Carbon::now('UTC');
            $resultado       = [];

            foreach ($prediccionesRaw as $elemento) {
                if (!is_array($elemento)) continue;

                $ds    = $elemento['ds']         ?? $elemento['timestamp'] ?? $elemento[0] ?? null;
                $y     = $elemento['yhat']       ?? $elemento['value']     ?? $elemento[1] ?? null;
                $lower = $elemento['yhat_lower'] ?? $elemento[2] ?? null;
                $upper = $elemento['yhat_upper'] ?? $elemento[3] ?? null;

                if (!$ds || !is_numeric($y)) continue;
                if (!Carbon::parse($ds)->greaterThan($ahora)) continue;

                $resultado[] = [
                    'time'       => $ds,
                    'yhat'       => round((float) $y, 4),
                    'yhat_lower' => $lower !== null ? round((float) $lower, 4) : null,
                    'yhat_upper' => $upper !== null ? round((float) $upper, 4) : null,
                ];
            }

            return response()->json([
                'device'   => $etiqueta,
                'nombre'   => $dispositivo->nombre,
                'hours'    => $horas,
                'forecast' => $resultado,
            ]);

        } catch (\Throwable $e) {
            Log::error('[API] forecast exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error generando predicción.'], 500);
        }
    }

    private function buscarDispositivo(Request $request, $id)
    {
        return $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1)
            ->where('dispositivos.id', $id)
            ->first();
    }
}
