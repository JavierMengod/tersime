<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfluxController extends Controller
{
    private string $grafanaUrl;
    private int $datasourceId;
    private string $bucket;
    private ?string $token;

    public function __construct()
    {
        $this->grafanaUrl = config('app.grafana_api_ds_query', 'http://155.210.71.113:3000/api/ds/query');
        $this->datasourceId = (int) (config('app.grafana_datasource_id', 3));
        $this->bucket = config('app.influx_bucket', 'PINZAS');
        $this->token = env('GRAFANA_API_KEY') ?: null;
    }

    // ---------------------------------------------------------
    //  PUBLIC API
    // ---------------------------------------------------------

    public function consumoTotal(string $device, string $start, string $stop): float
    {
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: $startFlux, stop: $stopFlux)
  |> filter(fn: (r) => 
        r._measurement == "hourly" and 
        r._field == "kwh" and 
        r.name == "{$device}"
     )
  |> group()
  |> sum(column: "_value")
FLUX;

        $values = $this->executeFluxReturningValues($flux);

        $total = 0.0;

        if (!empty($values)) {
            $first = $values[0];

            if (is_numeric($first)) {
                $total = (float) $first;
            } elseif (is_array($first)) {
                if (isset($first['_value']) && is_numeric($first['_value'])) {
                    $total = (float) $first['_value'];
                } else {
                    foreach ($first as $v) {
                        if (is_numeric($v)) {
                            $total = (float) $v;
                            break;
                        }
                    }
                }
            }
        }

        return $total;
    }

    public function datosHorarios(string $device, string $start, string $stop): array
    {
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: $startFlux, stop: $stopFlux)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> sort(columns:["_time"])
FLUX;

        return $this->executeFluxReturningMap($flux);
    }

    public function datosDiarios(string $device, string $start, string $stop): array
    {
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: $startFlux, stop: $stopFlux)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> aggregateWindow(every: 1d, fn: sum)
  |> keep(columns: ["_time", "_value"])
  |> sort(columns: ["_time"])
FLUX;

        return $this->executeFluxReturningMap($flux);
    }

    /**
     * Devuelve un array con keys: mean, stddev, max, min, sum
     * Cada valor es float|null si no hay datos.
     */
    public function datosEstadisticos(string $device, string $start, string $stop): array
    {
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux = $this->fluxTimeLiteral($stop, false);

        $baseFilter = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: $startFlux, stop: $stopFlux)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
FLUX;

        $queries = [
            'mean' => $baseFilter . "\n  |> mean(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)",
            'stddev' => $baseFilter . "\n  |> stddev(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)",
            'max' => $baseFilter . "\n  |> max(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)",
            'min' => $baseFilter . "\n  |> min(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)",
            'sum' => $baseFilter . "\n  |> sum(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)",
        ];

        $result = [
            'mean' => null,
            'stddev' => null,
            'max' => null,
            'min' => null,
            'sum' => null,
        ];

        foreach ($queries as $k => $q) {
            $vals = $this->executeFluxReturningValues($q);
            if (!empty($vals) && is_numeric($vals[0])) {
                $result[$k] = (float) $vals[0];
            } elseif (!empty($vals) && is_array($vals[0])) {
                // intentar extraer _value si viene como frame
                $first = $vals[0];
                if (isset($first['_value']) && is_numeric($first['_value'])) {
                    $result[$k] = (float) $first['_value'];
                }
            }
        }

        return $result;
    }

    public function resumen(string $device, string $start, string $stop): array
    {
        try {
            $total = $this->consumoTotal($device, $start, $stop);
            $horas = $this->datosHorarios($device, $start, $stop);
            $dias = $this->datosDiarios($device, $start, $stop);

            return compact('total', 'horas', 'dias');

        } catch (\Throwable $e) {
            Log::error('[InfluxController] resumen ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['total' => 0, 'horas' => [], 'dias' => []];
        }
    }

    public function mediaHistoricaPeriodo(string $device, string $start, string $stop): ?float
    {
        try {
            $startDt = Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
            $stopDt = Carbon::createFromFormat('Y-m-d', $stop)->endOfDay();
            $diffSeconds = $stopDt->timestamp - $startDt->timestamp;

            if ($diffSeconds <= 0) {
                return null;
            }
            $histStop = $startDt->copy()->subSecond();
            $histStart = $histStop->copy()->subSeconds($diffSeconds);

            $hs = $histStart->format('Y-m-d');
            $he = $histStop->format('Y-m-d');

            $historicalTotal = $this->consumoTotal($device, $hs, $he);

            return $historicalTotal;

        } catch (\Throwable $e) {
            Log::error('[InfluxController] mediaHistoricaPeriodo ERROR', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Consulta InfluxDB directamente (sin pasar por Grafana) y devuelve
     * timestamps + valores horarios del último año para un dispositivo.
     * Usado exclusivamente por PrediccionController.
     */
    public function datosParaPrediccion(string $device, string $stop): array
    {
        $influxUrl = env('INFLUXDB_URL', 'http://localhost:8086')
            . '/api/v2/query?org=' . env('INFLUXDB_ORG', 'tersime');
        $token    = env('INFLUXDB_TOKEN', '');
        $stopFlux = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -1y, stop: {$stopFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> sort(columns: ["_time"])
  |> keep(columns: ["_time", "_value"])
FLUX;

        $response = Http::withHeaders([
            'Authorization' => "Token {$token}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ])->timeout(30)->post($influxUrl, [
            'query'   => $flux,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ]);

        if (!$response->successful()) {
            Log::error('[InfluxController] datosParaPrediccion ERROR', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            return ['timestamps' => [], 'values' => []];
        }

        $timestamps = [];
        $values     = [];
        $timeIdx    = null;
        $valueIdx   = null;
        $headerSeen = false;

        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;

            $cols = str_getcsv($line);

            if (!$headerSeen) {
                $headerSeen = true;
                $timeIdx    = array_search('_time', $cols);
                $valueIdx   = array_search('_value', $cols);
                continue;
            }

            if ($timeIdx === false || $valueIdx === false) continue;
            if (!isset($cols[$timeIdx], $cols[$valueIdx])) continue;
            if (!is_numeric($cols[$valueIdx])) continue;

            $timestamps[] = $cols[$timeIdx];
            $values[]     = (float) $cols[$valueIdx];
        }

        Log::info('[InfluxController] datosParaPrediccion OK', [
            'device' => $device,
            'puntos' => count($timestamps),
        ]);

        return ['timestamps' => $timestamps, 'values' => $values];
    }

    /**
     * Devuelve el último valor kWh registrado en las últimas 24 h para un dispositivo.
     * Retorna null si no hay datos o el dispositivo no responde.
     */
    public function ultimoValor(string $device): ?float
    {
        $influxUrl = env('INFLUXDB_URL', 'http://localhost:8086')
            . '/api/v2/query?org=' . env('INFLUXDB_ORG', 'tersime');
        $token = env('INFLUXDB_TOKEN', '');

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> last()
  |> keep(columns: ["_value"])
FLUX;

        $response = Http::withHeaders([
            'Authorization' => "Token {$token}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ])->timeout(10)->post($influxUrl, [
            'query'   => $flux,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ]);

        if (!$response->successful()) {
            Log::warning('[InfluxController] ultimoValor fallo HTTP', [
                'device' => $device,
                'status' => $response->status(),
            ]);
            return null;
        }

        $valueIdx   = null;
        $headerSeen = false;

        foreach (explode("\n", $response->body()) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;

            $cols = str_getcsv($line);
            if (!$headerSeen) {
                $headerSeen = true;
                $valueIdx   = array_search('_value', $cols);
                continue;
            }

            if ($valueIdx === false || !isset($cols[$valueIdx])) continue;
            if (is_numeric($cols[$valueIdx])) return (float) $cols[$valueIdx];
        }

        return null;
    }

    // ---------------------------------------------------------
    //  FLUX EXEC / HELPERS
    // ---------------------------------------------------------

    private function queryGrafana(string $flux)
    {
        // Mantener logs de las consultas (peticiones a Grafana)
        //Log::info('[InfluxController] queryGrafana ejecutando', ['flux' => $flux]);

        if (empty($this->token)) {
            Log::warning('[InfluxController] queryGrafana: NO API KEY');
        }

        $body = [
            "queries" => [
                [
                    "refId" => "A",
                    "datasourceId" => $this->datasourceId,
                    "query" => $flux,
                    "format" => "table"
                ]
            ]
        ];

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
            if ($this->token) {
                $headers['Authorization'] = "Bearer {$this->token}";
            }

            $response = Http::withHeaders($headers)
                ->timeout(90)
                ->withOptions(['verify' => false])
                ->post($this->grafanaUrl, $body);

            if ($response->failed()) {
                Log::error('[InfluxController] Grafana ERROR', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            Log::info('[InfluxController] queryGrafana OK', ['length' => strlen($response->body())]);
            return $response->json();

        } catch (\Throwable $e) {
            Log::error('[InfluxController] queryGrafana EXCEPTION', [
                'error' => $e->getMessage(),
                'flux' => $flux
            ]);
            return [];
        }
    }

    private function executeFluxReturningValues(string $flux): array
    {
        $json = $this->queryGrafana($flux);
        return $this->extractValuesOnly($json);
    }

    private function executeFluxReturningMap(string $flux): array
    {
        $json = $this->queryGrafana($flux);
        return $this->extractMap($json);
    }

    // ---------------------------------------------------------
    //  DATA EXTRACTORS
    // ---------------------------------------------------------

    private function extractValuesOnly($json): array
    {
        $out = [];

        if (!isset($json['results'])) {
            return $out;
        }

        foreach ($json['results'] as $res) {
            foreach ($res['frames'] ?? [] as $frame) {

                if (!isset($frame['data']['values'])) {
                    continue;
                }

                $values = $frame['data']['values'];
                $fields = $frame['schema']['fields'] ?? null;

                if (is_array($fields) && count($fields) > 0) {
                    foreach ($fields as $idx => $f) {
                        $fname = $f['name'] ?? null;
                        if ($fname === '_value' && isset($values[$idx]) && is_array($values[$idx])) {
                            foreach ($values[$idx] as $v) {
                                if (is_numeric($v)) {
                                    $out[] = (float) $v;
                                }
                            }
                        }
                    }
                    continue;
                }

                if (count($values) >= 2 && is_array($values[0]) && is_array($values[1])) {
                    $headers = $values[0];
                    if (!empty($headers) && is_string($headers[0] ?? null)) {
                        $row = $values[1];
                        $i = array_search('_value', $headers, true);
                        if ($i !== false && isset($row[$i]) && is_numeric($row[$i])) {
                            $out[] = (float) $row[$i];
                            continue;
                        }
                    }
                }

                if (isset($values[1]) && is_array($values[1])) {
                    foreach ($values[1] as $v) {
                        if (is_numeric($v)) {
                            $out[] = (float) $v;
                        }
                    }
                    continue;
                }

                foreach ($values as $maybeRow) {
                    if (!is_array($maybeRow)) {
                        continue;
                    }
                    $found = null;
                    for ($j = count($maybeRow) - 1; $j >= 0; $j--) {
                        if (is_numeric($maybeRow[$j])) {
                            $found = (float) $maybeRow[$j];
                            break;
                        }
                    }
                    if ($found !== null) {
                        $out[] = $found;
                    }
                }
            }
        }

        return $out;
    }

    private function extractMap($json): array
    {
        $map = [];

        if (!isset($json['results'])) {
            return $map;
        }

        foreach ($json['results'] as $res) {
            foreach ($res['frames'] ?? [] as $frame) {

                $ts = $frame['data']['values'][0] ?? [];
                $vals = $frame['data']['values'][1] ?? [];

                $len = min(count($ts), count($vals));

                for ($i = 0; $i < $len; $i++) {
                    if (!is_numeric($vals[$i]))
                        continue;

                    $t = $this->normalizeTimestamp($ts[$i]);
                    if ($t) {
                        $map[$t] = (float) $vals[$i];
                    }
                }
            }
        }

        ksort($map);
        return $map;
    }

    private function normalizeTimestamp($t): ?string
    {
        if (!is_numeric($t)) {
            try {
                return Carbon::parse($t)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $sec = ($t > 1e12) ? intval($t / 1000) : intval($t);

        return gmdate('Y-m-d\TH:i:s\Z', $sec);
    }

    private function fluxTimeLiteral(string $ymd, bool $startOfDay = true): string
    {
        try {
            $dt = Carbon::createFromFormat('Y-m-d', $ymd, config('app.timezone'));
        } catch (\Throwable $e) {
            $dt = Carbon::parse($ymd);
        }

        $dt = $startOfDay ? $dt->startOfDay() : $dt->endOfDay();
        return $dt->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }

    // ---------------------------------------------------------
    //  FACTOR DE CARGA (CORREGIDO)
    // ---------------------------------------------------------

    // Cambiado a public para que InformeController pueda invocarlo
    public function factorCarga(string $device = "general", string $start = null, string $stop = null)
    {
        if (empty($device)) {
            Log::error("[InfluxController] factorCarga: parámetro device vacío");
            return null;
        }

        $start = $start ?? Carbon::now()->subDays(365)->format('Y-m-d');
        $stop = $stop ?? Carbon::now()->format('Y-m-d');

        $flux = <<<FLUX
hourly = from(bucket: "{$this->bucket}")
  |> range(start: {$this->fluxTimeLiteral($start, true)}, stop: {$this->fluxTimeLiteral($stop, false)})
  |> filter(fn: (r) =>
      r._measurement == "hourly" and
      r._field == "kwh" and
      r.name == "{$device}"
  )
  |> keep(columns: ["_time", "_value", "name"])

mean_values =
  hourly
    |> mean(column: "_value")
    |> rename(columns: {_value: "mean_kwh"})

max_values =
  hourly
    |> max(column: "_value")
    |> rename(columns: {_value: "max_kwh"})

join(
    tables: {mean: mean_values, max: max_values},
    on: ["name"]
)
  |> map(fn: (r) => ({
        name: r.name,
        _time: now(),
        _field: "factor_carga",
        _value: float(v: r.mean_kwh) / float(v: r.max_kwh)
    }))
  |> keep(columns: ["_value"])
  |> limit(n: 1)
FLUX;

        $json = $this->queryGrafana($flux);

        $vals = $this->extractValuesOnly($json);

        if (empty($vals)) {
            return null;
        }

        $potenciaPicoKw = (float) $vals[0];

        if ($potenciaPicoKw <= 0) {
            return null;
        }

        return $potenciaPicoKw;
    }

    /**
     * Devuelve la media por hora (promedio horario) para un dispositivo en un periodo.
     *
     * Retorna un array con keys ISO8601 UTC (ej: "2025-01-01T00:00:00Z") => mean_kwh (float).
     *
     * @param string $device nombre del dispositivo (campo "name" en Influx)
     * @param string $start  fecha YYYY-MM-DD
     * @param string $stop   fecha YYYY-MM-DD
     * @return array
     */
    public function mediaPorHora(string $device, string $start, string $stop): array
    {
        try {
            $tz = config('app.timezone', 'Europe/Madrid');

            $startFlux = $this->fluxTimeLiteral($start, true);
            $stopFlux = $this->fluxTimeLiteral($stop, false);

            // Fecha base fija para evitar problemas de DST
            $baseDate = '2025-01-01';

            $flux = <<<FLUX
import "date"
option timezone = "{$tz}"

from(bucket: "{$this->bucket}")
  |> range(start: $startFlux, stop: $stopFlux)
  |> filter(fn: (r) =>
       r._measurement == "hourly" and
       r._field == "kwh" and
       r["name"] == "{$device}"
  )
  |> map(fn: (r) => ({ r with hour: date.hour(t: r._time) }))
  |> group(columns: ["hour"])
  |> mean(column: "_value")
  |> keep(columns: ["hour", "_value"])
  |> map(fn: (r) => ({ r with hourStr: if r.hour < 10 then "0" + string(v: r.hour) else string(v: r.hour) }))
  |> map(fn: (r) => ({ r with _time: time(v: "{$baseDate}T" + r.hourStr + ":00:00+01:00") }))
  |> rename(columns: {_value: "mean_kwh"})
  |> keep(columns: ["_time", "mean_kwh"])
  |> sort(columns: ["_time"])
FLUX;

            // Esto devuelve un mapa ISO => mean_kwh
            $mapISO = $this->executeFluxReturningMap($flux);

            // Convertimos a '00'..'23' => mean_kwh
            $result = [];

            foreach ($mapISO as $ts => $val) {
                try {
                    $h = \Carbon\Carbon::parse($ts)
                        ->setTimezone('UTC')
                        ->format('H');

                    $result[$h] = (float) $val;

                } catch (\Throwable $e) {
                    \Log::warning('[InfluxController] mediaPorHora: timestamp inválido', [
                        'device' => $device,
                        'ts' => $ts,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Log final para ver exactamente qué se devuelve
            \Log::debug("[InfluxController][mediaPorHora][$device] Resultado final por hora: " . json_encode($result));

            return $result;

        } catch (\Throwable $e) {
            \Log::error('[InfluxController] mediaPorHora ERROR', [
                'device' => $device,
                'start' => $start,
                'stop' => $stop,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

}
