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
        $etiquetas = array_filter((array) $request->input('devices', []));
        $desde     = $request->input('from', '');
        $hasta     = $request->input('to', '');

        if (empty($etiquetas) || !$desde || !$hasta) {
            return response()->json([]);
        }

        $mapaDeNombres = auth()->user()->dispositivos
            ->whereIn('influx_tag', $etiquetas)
            ->mapWithKeys(fn($d) => [$d->influx_tag => $d->pivot->nombre])
            ->toArray();

        $conjuntoDatos = [];
        foreach ($etiquetas as $etiqueta) {
            $datos  = $influx->datosHorarios($etiqueta, $desde, $hasta);
            $puntos = [];

            foreach ($datos as $marca => $valor) {
                $puntos[] = ['x' => $marca, 'y' => round((float) $valor, 4)];
            }

            $conjuntoDatos[] = [
                'label' => $mapaDeNombres[$etiqueta] ?? $etiqueta,
                'data'  => $puntos,
            ];
        }

        return response()->json($conjuntoDatos);
    }

    public function verificarDispositivos(): array
    {
        try {
            $urlBase  = rtrim(
                Ajuste::get('grafana_base_url') ?: config('app.grafana_base_url', 'http://localhost:3000'),
                '/'
            );
            $claveApi = Ajuste::get('grafana_api_key') ?: env('GRAFANA_API_KEY');
            $bucket   = Ajuste::get('influxdb_bucket') ?: env('INFLUX_BUCKET', 'PINZAS');

            $cliente = Http::withHeaders([
                'Authorization' => 'Bearer ' . $claveApi,
                'Content-Type'  => 'application/json',
            ]);

            $respuestaFuentes = $cliente->get($urlBase . '/api/datasources');
            if ($respuestaFuentes->failed()) {
                Log::error('[GrafanaController] No se pudo obtener datasources de Grafana.', [
                    'estado' => $respuestaFuentes->status(),
                ]);
                return [];
            }

            $influx = collect($respuestaFuentes->json())->firstWhere('type', 'influxdb');
            if (!$influx) {
                Log::error('[GrafanaController] Datasource InfluxDB no encontrado en Grafana.');
                return [];
            }

            $consultaFlux = <<<FLUX
            from(bucket: "{$bucket}")
              |> range(start: 0)
              |> filter(fn: (r) => r._measurement == "hourly")
              |> filter(fn: (r) => r._field == "kwh")
              |> group(columns: ["dev_eui", "name"])
              |> last()
            FLUX;

            $respuestaConsulta = $cliente->post($urlBase . '/api/ds/query', [
                'queries' => [[
                    'datasourceId' => $influx['id'],
                    'refId'        => 'A',
                    'query'        => $consultaFlux,
                    'format'       => 'table',
                ]],
                'from' => 'now-1h',
                'to'   => 'now',
            ]);

            if ($respuestaConsulta->failed()) {
                Log::error('[GrafanaController] Error ejecutando query.', [
                    'estado' => $respuestaConsulta->status(),
                ]);
                return [];
            }

            $datos        = $respuestaConsulta->json();
            $dispositivos = [];

            foreach ($datos['results']['A']['frames'] ?? [] as $trama) {
                $campos            = $trama['schema']['fields'] ?? [];
                $valores           = $trama['data']['values']   ?? [];
                $etiquetas         = $campos[1]['labels']        ?? [];
                $nombreDispositivo = $etiquetas['name']          ?? null;
                $eui               = $etiquetas['dev_eui']       ?? null;

                if ($nombreDispositivo && $eui) {
                    $dispositivos[] = [
                        'name'    => $nombreDispositivo,
                        'dev_eui' => $eui,
                        'value'   => $valores[1][0] ?? null,
                        'unit'    => $valores[2][0] ?? null,
                        'time'    => $valores[0][0] ?? null,
                    ];
                }
            }

            Log::info('[GrafanaController] Resumen de dispositivos:', ['dispositivos' => $dispositivos]);
            return $dispositivos;

        } catch (\Throwable $e) {
            Log::error('[GrafanaController] Error consultando dispositivos: ' . $e->getMessage());
            return [];
        }
    }
}
