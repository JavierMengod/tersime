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

        $desde         = $request->input('from');
        $hasta         = $request->input('to');
        $idsDispositivos = $request->input('devices', []);

        $consulta = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1);

        if (!empty($idsDispositivos)) {
            $consulta->whereIn('dispositivos.id', $idsDispositivos);
        }

        $dispositivos = $consulta->get();
        $resultados   = [];
        $totalGlobal  = 0.0;

        foreach ($dispositivos as $dispositivo) {
            try {
                $total = $this->influx->consumoTotal($dispositivo->etiqueta_influx, $desde, $hasta);
                $totalGlobal += $total;
                $resultados[] = [
                    'id'              => $dispositivo->id,
                    'etiqueta_influx' => $dispositivo->etiqueta_influx,
                    'nombre'          => $dispositivo->nombre,
                    'total_kwh'       => round($total, 4),
                ];
            } catch (\Throwable $e) {
                Log::warning('[API] consumption summary error', [
                    'device' => $dispositivo->etiqueta_influx,
                    'error'  => $e->getMessage(),
                ]);
                $resultados[] = [
                    'id'              => $dispositivo->id,
                    'etiqueta_influx' => $dispositivo->etiqueta_influx,
                    'nombre'          => $dispositivo->nombre,
                    'total_kwh'       => null,
                    'error'           => 'No se pudieron obtener datos.',
                ];
            }
        }

        return response()->json([
            'from'            => $desde,
            'to'              => $hasta,
            'devices'         => $resultados,
            'grand_total_kwh' => round($totalGlobal, 4),
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

        $desde           = $request->input('from');
        $hasta           = $request->input('to');
        $tarifa          = (float) $request->input('rate');
        $idsDispositivos = $request->input('devices', []);

        $consulta = $request->user()
            ->dispositivos()
            ->wherePivot('habilitado', 1);

        if (!empty($idsDispositivos)) {
            $consulta->whereIn('dispositivos.id', $idsDispositivos);
        }

        $dispositivos = $consulta->get();
        $resultados   = [];
        $totalGlobal  = 0.0;
        $costeTotal   = 0.0;

        foreach ($dispositivos as $dispositivo) {
            try {
                $total       = $this->influx->consumoTotal($dispositivo->etiqueta_influx, $desde, $hasta);
                $coste       = $total * $tarifa;
                $totalGlobal += $total;
                $costeTotal  += $coste;
                $resultados[] = [
                    'id'              => $dispositivo->id,
                    'etiqueta_influx' => $dispositivo->etiqueta_influx,
                    'nombre'          => $dispositivo->nombre,
                    'total_kwh'       => round($total, 4),
                    'cost'            => round($coste, 2),
                ];
            } catch (\Throwable $e) {
                Log::warning('[API] consumption cost error', [
                    'device' => $dispositivo->etiqueta_influx,
                    'error'  => $e->getMessage(),
                ]);
                $resultados[] = [
                    'id'              => $dispositivo->id,
                    'etiqueta_influx' => $dispositivo->etiqueta_influx,
                    'nombre'          => $dispositivo->nombre,
                    'total_kwh'       => null,
                    'cost'            => null,
                    'error'           => 'No se pudieron obtener datos.',
                ];
            }
        }

        return response()->json([
            'from'             => $desde,
            'to'               => $hasta,
            'rate_per_kwh'     => $tarifa,
            'devices'          => $resultados,
            'grand_total_kwh'  => round($totalGlobal, 4),
            'grand_total_cost' => round($costeTotal, 2),
        ]);
    }
}
