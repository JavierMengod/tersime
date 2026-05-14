<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

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


    public function dispositivos()
    {
        $dispositivos = auth()->user()->dispositivos;
        $dispositivosGrafana = $this->dispositivosGrafana();

        return view('monitorizacion.dispositivos', compact('dispositivos', 'dispositivosGrafana'));
    }

    public static function dispositivosGrafana()
    {
        // Endpoint directo de InfluxDB
        $influxUrl = env('INFLUXDB_URL', 'http://localhost:8086') . '/api/v2/query?org=' . env('INFLUXDB_ORG', 'tersime');

        $token = env('INFLUXDB_TOKEN', '');

        // Consulta Flux
        $fluxQuery = <<<'FLUX'
            from(bucket:"PINZAS")
            |> range(start: 0)
            |> filter(fn: (r) => r._measurement == "daily" and r._field == "kwh_total")
            |> distinct(column: "name")
            |> keep(columns: ["name"])
        FLUX;

        // Body JSON
        $body = [
            'query' => $fluxQuery,
            'dialect' => [
                'header' => true,
                'delimiter' => ',',
            ],
        ];

        // Realizar la llamada HTTP
        $response = Http::withHeaders([
            'Authorization' => "Token {$token}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/csv',
        ])->post($influxUrl, $body);

        Log::debug('InfluxDB response status: ' . $response->status());

        if ($response->successful()) {
            $csvRaw = $response->body();
            Log::debug('InfluxDB raw CSV response:', ['csv' => $csvRaw]);

            // Convertimos CSV a array de líneas
            $lines = array_filter(explode("\n", $csvRaw));

            $dispositivos = [];
            foreach ($lines as $index => $line) {
                // Saltar cabecera
                if ($index === 0) {
                    continue;
                }

                $parts = str_getcsv($line);
                $name = $parts[3] ?? null;

                if (!empty($name)) {
                    $dispositivos[] = $name;
                }
            }

            // Limpiamos duplicados y reindexamos
            $dispositivos = array_values(array_unique($dispositivos));

            // Log para ver el resultado final
            Log::info('Dispositivos recibidos desde InfluxDB:', $dispositivos);

            return $dispositivos;
        }

        // Log error
        Log::error('Error consultando InfluxDB', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [];
    }

    public static function checkDevices()
    {
        try {
            $client = new Client([
                'base_uri' => config('app.grafana_base_url') . '/api/',
                'headers' => [
                    'Authorization' => 'Bearer ' . env('GRAFANA_API_KEY'),
                    'Content-Type' => 'application/json',
                ]
            ]);

            // 1. Consultamos el datasource
            $datasources = $client->get('datasources');
            $datasources = json_decode($datasources->getBody(), true);

            $influx = collect($datasources)->firstWhere('type', 'influxdb');
            if (!$influx) {
                Log::error("Datasource InfluxDB no encontrado en Grafana.");
                return [];
            }

            $datasourceId = $influx['id'];

            // 2. Query con Flux
            $fluxQuery = '
            from(bucket: "PINZAS")
              |> range(start: 0)
              |> filter(fn: (r) => r._measurement == "hourly")
              |> filter(fn: (r) => r._field == "kwh")
              |> group(columns: ["dev_eui", "name"])
              |> last()
        ';

            $response = $client->post('ds/query', [
                'json' => [
                    "queries" => [
                        [
                            "datasourceId" => $datasourceId,
                            "refId" => "A",
                            "query" => $fluxQuery,
                            "format" => "table"
                        ]
                    ],
                    "from" => "now-1h",
                    "to" => "now"
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            // 3. Parseamos los datos para un array simple
            $devices = [];
            if (isset($data['results']['A']['frames'])) {
                foreach ($data['results']['A']['frames'] as $frame) {
                    $fields = $frame['schema']['fields'] ?? [];
                    $values = $frame['data']['values'] ?? [];

                    // Sacamos labels del campo _value (índice 1)
                    $labels = $fields[1]['labels'] ?? [];

                    $deviceName = $labels['name'] ?? null;
                    $devEui = $labels['dev_eui'] ?? null;
                    $value = $values[1][0] ?? null; // valor kWh
                    $time = $values[0][0] ?? null;   // timestamp
                    $unit = $values[2][0] ?? null;   // unidad, ej: kwh

                    if ($deviceName && $devEui) {
                        $devices[] = [
                            'name' => $deviceName,
                            'dev_eui' => $devEui,
                            'value' => $value,
                            'unit' => $unit,
                            'time' => $time,
                        ];
                    }
                }
            }

            // 4. Logueamos un resumen pequeño
            Log::info('Resumen de dispositivos:', ['devices' => $devices]);

            return $devices;

        } catch (\Exception $e) {
            Log::error("Error consultando dispositivos: " . $e->getMessage());
            return [];
        }
    }

}
