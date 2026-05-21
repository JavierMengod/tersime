<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Services\InfluxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrafanaController extends Controller
{
    /**
     * Devuelve series temporales de consumo para los dispositivos solicitados,
     * con el nombre amigable del usuario en lugar del identificador de InfluxDB.
     */
    public function series(Request $request, InfluxService $influx): JsonResponse
    {
        $urls  = array_filter((array) $request->input('devices', []));
        $desde = $request->input('from', '');
        $hasta = $request->input('to', '');

        if (empty($urls) || !$desde || !$hasta) {
            return response()->json([]);
        }

        $mapaDeNombres = auth()->user()->dispositivos
            ->whereIn('influx_tag', $urls)
            ->mapWithKeys(fn($d) => [$d->influx_tag => $d->pivot->nombre])
            ->toArray();

        $conjuntoDatos = [];
        foreach ($urls as $url) {
            $datos = $influx->datosHorarios($url, $desde, $hasta);

            $puntos = [];
            foreach ($datos as $ts => $val) {
                $puntos[] = ['x' => $ts, 'y' => round((float) $val, 4)];
            }

            $conjuntoDatos[] = [
                'label' => $mapaDeNombres[$url] ?? $url,
                'data'  => $puntos,
            ];
        }

        return response()->json($conjuntoDatos);
    }

    public function checkDevices(): array
    {
        try {
            $grafanaBase = rtrim(
                Ajuste::get('grafana_base_url') ?: config('app.grafana_base_url', 'http://localhost:3000'),
                '/'
            );
            $apiKey = Ajuste::get('grafana_api_key') ?: env('GRAFANA_API_KEY');
            $bucket = Ajuste::get('influxdb_bucket') ?: env('INFLUX_BUCKET', 'PINZAS');

            $cliente = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ]);

            $respuestaDs = $cliente->get($grafanaBase . '/api/datasources');
            if ($respuestaDs->failed()) {
                Log::error('[GrafanaController] No se pudo obtener datasources de Grafana.', [
                    'status' => $respuestaDs->status(),
                ]);
                return [];
            }

            $influx = collect($respuestaDs->json())->firstWhere('type', 'influxdb');
            if (!$influx) {
                Log::error('[GrafanaController] Datasource InfluxDB no encontrado en Grafana.');
                return [];
            }

            $fluxQuery = <<<FLUX
            from(bucket: "{$bucket}")
              |> range(start: 0)
              |> filter(fn: (r) => r._measurement == "hourly")
              |> filter(fn: (r) => r._field == "kwh")
              |> group(columns: ["dev_eui", "name"])
              |> last()
            FLUX;

            $respuestaQuery = $cliente->post($grafanaBase . '/api/ds/query', [
                'queries' => [[
                    'datasourceId' => $influx['id'],
                    'refId'        => 'A',
                    'query'        => $fluxQuery,
                    'format'       => 'table',
                ]],
                'from' => 'now-1h',
                'to'   => 'now',
            ]);

            if ($respuestaQuery->failed()) {
                Log::error('[GrafanaController] Error ejecutando query.', [
                    'status' => $respuestaQuery->status(),
                ]);
                return [];
            }

            $datos       = $respuestaQuery->json();
            $dispositivos = [];

            foreach ($datos['results']['A']['frames'] ?? [] as $frame) {
                $campos      = $frame['schema']['fields'] ?? [];
                $valores     = $frame['data']['values']   ?? [];
                $etiquetas   = $campos[1]['labels']        ?? [];
                $nombreDispositivo = $etiquetas['name']    ?? null;
                $devEui      = $etiquetas['dev_eui']       ?? null;

                if ($nombreDispositivo && $devEui) {
                    $dispositivos[] = [
                        'name'    => $nombreDispositivo,
                        'dev_eui' => $devEui,
                        'value'   => $valores[1][0] ?? null,
                        'unit'    => $valores[2][0] ?? null,
                        'time'    => $valores[0][0] ?? null,
                    ];
                }
            }

            Log::info('[GrafanaController] Resumen de dispositivos:', ['devices' => $dispositivos]);
            return $dispositivos;

        } catch (\Throwable $e) {
            Log::error('[GrafanaController] Error consultando dispositivos: ' . $e->getMessage());
            return [];
        }
    }
}
