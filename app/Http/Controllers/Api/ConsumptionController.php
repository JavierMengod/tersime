<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InfluxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsumptionController extends Controller
{
    protected InfluxService $influx;

    public function __construct(InfluxService $influx)
    {
        $this->influx = $influx;
    }

    public function summary(Request $request)
    {
        $request->validate([
            'from'      => 'required|date_format:Y-m-d',
            'to'        => 'required|date_format:Y-m-d|after_or_equal:from',
            'devices'   => 'nullable|array',
            'devices.*' => 'integer',
        ]);

        $from      = $request->input('from');
        $to        = $request->input('to');
        $deviceIds = $request->input('devices', []);

        $query = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1);

        if (!empty($deviceIds)) {
            $query->whereIn('dispositivos.id', $deviceIds);
        }

        $devices = $query->get();
        $results = [];
        $grandTotal = 0.0;

        foreach ($devices as $device) {
            try {
                $total = $this->influx->consumoTotal($device->etiqueta_influx, $from, $to);
                $grandTotal += $total;
                $results[] = [
                    'id'         => $device->id,
                    'etiqueta_influx' => $device->etiqueta_influx,
                    'nombre'     => $device->nombre,
                    'total_kwh'  => round($total, 4),
                ];
            } catch (\Throwable $e) {
                Log::warning('[API] consumption summary error', [
                    'device' => $device->etiqueta_influx,
                    'error'  => $e->getMessage(),
                ]);
                $results[] = [
                    'id'         => $device->id,
                    'etiqueta_influx' => $device->etiqueta_influx,
                    'nombre'     => $device->nombre,
                    'total_kwh'  => null,
                    'error'      => 'No se pudieron obtener datos.',
                ];
            }
        }

        return response()->json([
            'from'        => $from,
            'to'          => $to,
            'devices'     => $results,
            'grand_total_kwh' => round($grandTotal, 4),
        ]);
    }

    public function cost(Request $request)
    {
        $request->validate([
            'from'       => 'required|date_format:Y-m-d',
            'to'         => 'required|date_format:Y-m-d|after_or_equal:from',
            'devices'    => 'nullable|array',
            'devices.*'  => 'integer',
            'rate'       => 'required|numeric|min:0',
        ]);

        $from      = $request->input('from');
        $to        = $request->input('to');
        $rate      = (float) $request->input('rate');
        $deviceIds = $request->input('devices', []);

        $query = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1);

        if (!empty($deviceIds)) {
            $query->whereIn('dispositivos.id', $deviceIds);
        }

        $devices    = $query->get();
        $results    = [];
        $grandTotal = 0.0;
        $grandCost  = 0.0;

        foreach ($devices as $device) {
            try {
                $total = $this->influx->consumoTotal($device->etiqueta_influx, $from, $to);
                $cost  = $total * $rate;
                $grandTotal += $total;
                $grandCost  += $cost;
                $results[] = [
                    'id'         => $device->id,
                    'etiqueta_influx' => $device->etiqueta_influx,
                    'nombre'     => $device->nombre,
                    'total_kwh'  => round($total, 4),
                    'cost'       => round($cost, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('[API] consumption cost error', [
                    'device' => $device->etiqueta_influx,
                    'error'  => $e->getMessage(),
                ]);
                $results[] = [
                    'id'         => $device->id,
                    'etiqueta_influx' => $device->etiqueta_influx,
                    'nombre'     => $device->nombre,
                    'total_kwh'  => null,
                    'cost'       => null,
                    'error'      => 'No se pudieron obtener datos.',
                ];
            }
        }

        return response()->json([
            'from'             => $from,
            'to'               => $to,
            'rate_per_kwh'     => $rate,
            'devices'          => $results,
            'grand_total_kwh'  => round($grandTotal, 4),
            'grand_total_cost' => round($grandCost, 2),
        ]);
    }
}
