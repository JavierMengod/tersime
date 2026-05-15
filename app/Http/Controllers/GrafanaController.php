<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
    public function series(Request $request, InfluxController $influx): JsonResponse
    {
        $urls = array_filter((array) $request->input('devices', []));
        $from = $request->input('from', '');
        $to   = $request->input('to', '');

        if (empty($urls) || !$from || !$to) {
            return response()->json([]);
        }

        $nameMap = auth()->user()->dispositivos
            ->whereIn('influx_tag', $urls)
            ->mapWithKeys(fn($d) => [$d->influx_tag => $d->pivot->nombre])
            ->toArray();

        $datasets = [];
        foreach ($urls as $url) {
            $datos = $influx->datosHorarios($url, $from, $to);

            $points = [];
            foreach ($datos as $ts => $val) {
                $points[] = ['x' => $ts, 'y' => round((float) $val, 4)];
            }

            $datasets[] = [
                'label' => $nameMap[$url] ?? $url,
                'data'  => $points,
            ];
        }

        return response()->json($datasets);
    }

    public static function dispositivosGrafana(): array
    {
        $influxUrl = rtrim(Setting::get('influxdb_url') ?: env('INFLUXDB_URL', 'http://localhost:8086'), '/')
            . '/api/v2/query?org=' . (Setting::get('influxdb_org') ?: env('INFLUXDB_ORG', 'tersime'));
        $token  = Setting::get('influxdb_token') ?: env('INFLUXDB_TOKEN', '');
        $bucket = Setting::get('influxdb_bucket') ?: env('INFLUX_BUCKET', 'PINZAS');

        $fluxQuery = <<<FLUX
            from(bucket:"{$bucket}")
            |> range(start: 0)
            |> filter(fn: (r) => r._measurement == "daily" and r._field == "kwh_total")
            |> distinct(column: "name")
            |> keep(columns: ["name"])
        FLUX;

        $response = Http::withHeaders([
            'Authorization' => "Token {$token}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ])->post($influxUrl, [
            'query'   => $fluxQuery,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ]);

        Log::debug('[GrafanaController] dispositivosGrafana status: ' . $response->status());

        if (!$response->successful()) {
            Log::error('[GrafanaController] Error consultando InfluxDB', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        $lines        = array_filter(explode("\n", $response->body()));
        $dispositivos = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) continue;

            $parts = str_getcsv($line);
            $name  = $parts[3] ?? null;

            if (!empty($name)) {
                $dispositivos[] = $name;
            }
        }

        $dispositivos = array_values(array_unique($dispositivos));

        Log::info('[GrafanaController] Dispositivos desde InfluxDB:', $dispositivos);

        return $dispositivos;
    }

    public static function checkDevices(): array
    {
        try {
            $grafanaBase = rtrim(
                Setting::get('grafana_base_url') ?: config('app.grafana_base_url', 'http://localhost:3000'),
                '/'
            );
            $apiKey = Setting::get('grafana_api_key') ?: env('GRAFANA_API_KEY');
            $bucket = Setting::get('influxdb_bucket') ?: env('INFLUX_BUCKET', 'PINZAS');

            $client = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ]);

            $dsResponse = $client->get($grafanaBase . '/api/datasources');
            if ($dsResponse->failed()) {
                Log::error('[GrafanaController] No se pudo obtener datasources de Grafana.', [
                    'status' => $dsResponse->status(),
                ]);
                return [];
            }

            $influx = collect($dsResponse->json())->firstWhere('type', 'influxdb');
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

            $queryResponse = $client->post($grafanaBase . '/api/ds/query', [
                'queries' => [[
                    'datasourceId' => $influx['id'],
                    'refId'        => 'A',
                    'query'        => $fluxQuery,
                    'format'       => 'table',
                ]],
                'from' => 'now-1h',
                'to'   => 'now',
            ]);

            if ($queryResponse->failed()) {
                Log::error('[GrafanaController] Error ejecutando query.', [
                    'status' => $queryResponse->status(),
                ]);
                return [];
            }

            $data    = $queryResponse->json();
            $devices = [];

            foreach ($data['results']['A']['frames'] ?? [] as $frame) {
                $fields     = $frame['schema']['fields'] ?? [];
                $values     = $frame['data']['values']   ?? [];
                $labels     = $fields[1]['labels']        ?? [];
                $deviceName = $labels['name']             ?? null;
                $devEui     = $labels['dev_eui']          ?? null;

                if ($deviceName && $devEui) {
                    $devices[] = [
                        'name'    => $deviceName,
                        'dev_eui' => $devEui,
                        'value'   => $values[1][0] ?? null,
                        'unit'    => $values[2][0] ?? null,
                        'time'    => $values[0][0] ?? null,
                    ];
                }
            }

            Log::info('[GrafanaController] Resumen de dispositivos:', ['devices' => $devices]);

            return $devices;

        } catch (\Throwable $e) {
            Log::error('[GrafanaController] Error consultando dispositivos: ' . $e->getMessage());
            return [];
        }
    }
}
