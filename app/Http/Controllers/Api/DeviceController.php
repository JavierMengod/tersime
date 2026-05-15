<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InfluxController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    protected InfluxController $influx;

    public function __construct(InfluxController $influx)
    {
        $this->influx = $influx;
    }

    public function index(Request $request)
    {
        $devices = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1)
            ->get()
            ->map(function ($d) {
                return [
                    'id'         => $d->id,
                    'influx_tag' => $d->influx_tag,
                    'nombre'     => $d->nombre,
                ];
            });

        return response()->json($devices);
    }

    public function current(Request $request, $id)
    {
        $device = $this->findDevice($request, $id);

        if (!$device) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $value = $this->influx->ultimoValor($device->influx_tag);

        return response()->json([
            'device'     => $device->influx_tag,
            'nombre'     => $device->nombre,
            'value_kwh'  => $value,
            'has_data'   => $value !== null,
        ]);
    }

    public function consumption(Request $request, $id)
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $device = $this->findDevice($request, $id);

        if (!$device) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $from = $request->input('from');
        $to   = $request->input('to');
        $tag  = $device->influx_tag;

        $total  = $this->influx->consumoTotal($tag, $from, $to);
        $hourly = $this->influx->datosHorarios($tag, $from, $to);
        $daily  = $this->influx->datosDiarios($tag, $from, $to);

        return response()->json([
            'device'        => $tag,
            'nombre'        => $device->nombre,
            'from'          => $from,
            'to'            => $to,
            'total_kwh'     => round($total, 4),
            'hourly'        => $hourly,
            'daily'         => $daily,
        ]);
    }

    public function stats(Request $request, $id)
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $device = $this->findDevice($request, $id);

        if (!$device) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $from  = $request->input('from');
        $to    = $request->input('to');
        $tag   = $device->influx_tag;

        $stats = $this->influx->datosEstadisticos($tag, $from, $to);
        $fc    = $this->influx->factorCarga($tag, $from, $to);

        return response()->json([
            'device'        => $tag,
            'nombre'        => $device->nombre,
            'from'          => $from,
            'to'            => $to,
            'mean_kwh'      => $stats['mean'] !== null ? round($stats['mean'], 4) : null,
            'stddev_kwh'    => $stats['stddev'] !== null ? round($stats['stddev'], 4) : null,
            'max_kwh'       => $stats['max'] !== null ? round($stats['max'], 4) : null,
            'min_kwh'       => $stats['min'] !== null ? round($stats['min'], 4) : null,
            'total_kwh'     => $stats['sum'] !== null ? round($stats['sum'], 4) : null,
            'load_factor'   => $fc !== null ? round($fc, 4) : null,
        ]);
    }

    public function forecast(Request $request, $id)
    {
        $request->validate([
            'hours' => 'sometimes|integer|min:1|max:168',
        ]);

        $device = $this->findDevice($request, $id);

        if (!$device) {
            return response()->json(['message' => 'Dispositivo no encontrado.'], 404);
        }

        $tag   = $device->influx_tag;
        $hours = (int) $request->input('hours', 24);
        $stop  = Carbon::now()->format('Y-m-d');

        $urlPredictor = env('PREDICTOR_URL');
        if (!$urlPredictor) {
            return response()->json(['message' => 'Servicio de predicción no configurado.'], 503);
        }

        try {
            $cacheKey = 'pred_training_' . $tag . '_' . $stop;
            $data = Cache::remember($cacheKey, 3600, function () use ($tag, $stop) {
                return $this->influx->datosParaPrediccion($tag, $stop);
            });

            if (empty($data['timestamps'])) {
                return response()->json(['message' => 'Sin datos históricos para este dispositivo.'], 422);
            }

            $predResponse = Http::timeout(120)->asJson()->post($urlPredictor, [
                'timestamps'   => $data['timestamps'],
                'values'       => $data['values'],
                'predic_hours' => $hours,
            ]);

            if ($predResponse->failed()) {
                Log::error('[API] forecast predictor error', ['status' => $predResponse->status()]);
                return response()->json(['message' => 'Error en el servicio de predicción.'], 502);
            }

            $json     = $predResponse->json();
            $pred_raw = $json['predichos'] ?? $json['predictions'] ?? $json['data'] ?? [];
            $now      = Carbon::now('UTC');
            $result   = [];

            foreach ($pred_raw as $item) {
                if (!is_array($item)) continue;

                $ds    = $item['ds']         ?? $item['timestamp'] ?? $item[0] ?? null;
                $y     = $item['yhat']       ?? $item['value']     ?? $item[1] ?? null;
                $lower = $item['yhat_lower'] ?? $item[2] ?? null;
                $upper = $item['yhat_upper'] ?? $item[3] ?? null;

                if (!$ds || !is_numeric($y)) continue;
                if (!Carbon::parse($ds)->greaterThan($now)) continue;

                $result[] = [
                    'time'       => $ds,
                    'yhat'       => round((float) $y, 4),
                    'yhat_lower' => $lower !== null ? round((float) $lower, 4) : null,
                    'yhat_upper' => $upper !== null ? round((float) $upper, 4) : null,
                ];
            }

            return response()->json([
                'device'    => $tag,
                'nombre'    => $device->nombre,
                'hours'     => $hours,
                'forecast'  => $result,
            ]);

        } catch (\Throwable $e) {
            Log::error('[API] forecast exception', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error generando predicción.'], 500);
        }
    }

    private function findDevice(Request $request, $id)
    {
        return $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1)
            ->where('dispositivos.id', $id)
            ->first();
    }
}
