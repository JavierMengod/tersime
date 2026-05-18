<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfluxService
{
    private string $bucket;
    private string $influxUrl;
    private string $influxToken;

    public function __construct()
    {
        $this->bucket      = Setting::get('influxdb_bucket') ?: config('tersime.influxdb.bucket', 'PINZAS');
        $this->influxUrl   = rtrim(Setting::get('influxdb_url') ?: config('tersime.influxdb.url', 'http://localhost:8086'), '/')
            . '/api/v2/query?org=' . (Setting::get('influxdb_org') ?: config('tersime.influxdb.org', 'tersime'));
        $this->influxToken = Setting::get('influxdb_token') ?: config('tersime.influxdb.token', '');

        if (empty($this->influxToken)) {
            Log::warning('[InfluxService] influxdb_token no configurado — las queries fallarán con HTTP 401');
        }
        if (empty($this->bucket)) {
            Log::warning('[InfluxService] influxdb_bucket no configurado');
        }
    }

    // ---------------------------------------------------------
    //  PUBLIC API
    // ---------------------------------------------------------

    public function consumoTotal(string $device, string $start, string $stop): float
    {
        $device    = $this->sanitizeTag($device);
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) =>
        r._measurement == "hourly" and
        r._field == "kwh" and
        r.name == "{$device}"
     )
  |> group()
  |> sum(column: "_value")
FLUX;

        foreach ($this->query($flux) as $row) {
            if (isset($row['_value']) && is_numeric($row['_value'])) {
                return (float) $row['_value'];
            }
        }

        return 0.0;
    }

    public function datosHorarios(string $device, string $start, string $stop): array
    {
        $device    = $this->sanitizeTag($device);
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> sort(columns: ["_time"])
FLUX;

        return $this->rowsToMap($this->query($flux), '_time', '_value');
    }

    public function datosDiarios(string $device, string $start, string $stop): array
    {
        $device    = $this->sanitizeTag($device);
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> aggregateWindow(every: 1d, fn: sum)
  |> keep(columns: ["_time", "_value"])
  |> sort(columns: ["_time"])
FLUX;

        return $this->rowsToMap($this->query($flux), '_time', '_value');
    }

    public function datosEstadisticos(string $device, string $start, string $stop): array
    {
        $device    = $this->sanitizeTag($device);
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
import "math"

data = from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) =>
       r._measurement == "hourly" and
       r._field == "kwh" and
       r.name == "{$device}"
  )
  |> group()

data
  |> reduce(
      identity: {n: 0.0, sum: 0.0, min: 1.0e15, max: -1.0e15, sumSq: 0.0},
      fn: (r, accumulator) => ({
          n:     accumulator.n + 1.0,
          sum:   accumulator.sum + r._value,
          min:   if r._value < accumulator.min   then r._value else accumulator.min,
          max:   if r._value > accumulator.max   then r._value else accumulator.max,
          sumSq: accumulator.sumSq + r._value * r._value
      })
  )
  |> map(fn: (r) => ({
      mean:   if r.n > 0.0 then r.sum / r.n else 0.0,
      stddev: if r.n > 1.0 then math.sqrt(x: (r.sumSq - r.sum * r.sum / r.n) / (r.n - 1.0)) else 0.0,
      max:    if r.max > -1.0e15 then r.max else 0.0,
      min:    if r.min <  1.0e15 then r.min else 0.0,
      sum:    r.sum
  }))
  |> keep(columns: ["mean", "stddev", "max", "min", "sum"])
FLUX;

        $result = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];
        foreach ($this->query($flux) as $row) {
            foreach (['mean', 'stddev', 'max', 'min', 'sum'] as $key) {
                if (isset($row[$key]) && is_numeric($row[$key])) {
                    $result[$key] = (float) $row[$key];
                }
            }
            break;
        }

        return $result;
    }

    public function resumen(string $device, string $start, string $stop): array
    {
        try {
            $horas = $this->datosHorarios($device, $start, $stop);

            $total = array_sum($horas);

            // Aggregate hourly into daily using local timezone so 23:00 UTC = 00:00 Madrid
            // falls in the correct calendar day.
            $tz   = config('app.timezone', 'Europe/Madrid');
            $dias = [];
            foreach ($horas as $ts => $kwh) {
                $day = Carbon::parse($ts)->setTimezone($tz)->startOfDay()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
                $dias[$day] = ($dias[$day] ?? 0.0) + (float) $kwh;
            }
            ksort($dias);

            return ['total' => $total, 'horas' => $horas, 'dias' => $dias];
        } catch (\Throwable $e) {
            Log::error('[InfluxService] resumen ERROR', ['error' => $e->getMessage()]);
            return ['total' => 0, 'horas' => [], 'dias' => []];
        }
    }

    public function mediaHistoricaPeriodo(string $device, string $start, string $stop): ?float
    {
        try {
            $startDt     = Carbon::createFromFormat('Y-m-d', $start)->startOfDay();
            $stopDt      = Carbon::createFromFormat('Y-m-d', $stop)->endOfDay();
            $diffSeconds = $stopDt->timestamp - $startDt->timestamp;

            if ($diffSeconds <= 0) return null;

            $histStop  = $startDt->copy()->subSecond();
            $histStart = $histStop->copy()->subSeconds($diffSeconds);

            return $this->consumoTotal($device, $histStart->format('Y-m-d'), $histStop->format('Y-m-d'));
        } catch (\Throwable $e) {
            Log::error('[InfluxService] mediaHistoricaPeriodo ERROR', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function factorCarga(string $device = 'general', ?string $start = null, ?string $stop = null): ?float
    {
        $device = $this->sanitizeTag($device);
        if (empty($device)) {
            Log::error('[InfluxService] factorCarga: parámetro device vacío');
            return null;
        }

        $start     = $start ?? Carbon::now()->subDays(365)->format('Y-m-d');
        $stop      = $stop  ?? Carbon::now()->format('Y-m-d');
        $startFlux = $this->fluxTimeLiteral($start, true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
hourly = from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) =>
      r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}"
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

        foreach ($this->query($flux) as $row) {
            if (isset($row['_value']) && is_numeric($row['_value'])) {
                $v = (float) $row['_value'];
                return $v > 0 ? $v : null;
            }
        }

        Log::info('[InfluxService] factorCarga sin resultado (sin datos en el período)', [
            'device' => $device,
            'start'  => $start,
            'stop'   => $stop,
        ]);
        return null;
    }

    public function mediaPorHora(string $device, string $start, string $stop): array
    {
        $device = $this->sanitizeTag($device);
        try {
            $tz        = config('app.timezone', 'Europe/Madrid');
            $baseDate  = '2025-01-01';
            $startFlux = $this->fluxTimeLiteral($start, true);
            $stopFlux  = $this->fluxTimeLiteral($stop, false);

            $flux = <<<FLUX
import "date"
option timezone = "{$tz}"

from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
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
  |> keep(columns: ["_time", "_value"])
  |> sort(columns: ["_time"])
FLUX;

            $result = [];
            foreach ($this->query($flux) as $row) {
                if (!isset($row['_time'], $row['_value']) || !is_numeric($row['_value'])) continue;
                try {
                    $h          = Carbon::parse($row['_time'])->format('H');
                    $result[$h] = (float) $row['_value'];
                } catch (\Throwable $e) {
                    Log::warning('[InfluxService] mediaPorHora: timestamp inválido', [
                        'device' => $device,
                        'ts'     => $row['_time'],
                    ]);
                }
            }

            return $result;

        } catch (\Throwable $e) {
            Log::error('[InfluxService] mediaPorHora ERROR', [
                'device' => $device,
                'start'  => $start,
                'stop'   => $stop,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function datosParaPrediccion(string $device, string $stop): array
    {
        $device    = $this->sanitizeTag($device);
        $startFlux = $this->fluxTimeLiteral(Carbon::parse($stop)->subYear()->format('Y-m-d'), true);
        $stopFlux  = $this->fluxTimeLiteral($stop, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> sort(columns: ["_time"])
  |> keep(columns: ["_time", "_value"])
FLUX;

        $rows = $this->query($flux, 30);

        $timestamps = [];
        $values     = [];

        foreach ($rows as $row) {
            if (!isset($row['_time'], $row['_value']) || !is_numeric($row['_value'])) continue;
            $timestamps[] = $row['_time'];
            $values[]     = (float) $row['_value'];
        }

        Log::info('[InfluxService] datosParaPrediccion OK', [
            'device' => $device,
            'puntos' => count($timestamps),
        ]);

        return ['timestamps' => $timestamps, 'values' => $values];
    }

    public function ultimoValor(string $device): ?float
    {
        $device = $this->sanitizeTag($device);
        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
  |> last()
  |> keep(columns: ["_value"])
FLUX;

        foreach ($this->query($flux, 10) as $row) {
            if (isset($row['_value']) && is_numeric($row['_value'])) {
                return (float) $row['_value'];
            }
        }

        Log::warning('[InfluxService] ultimoValor: sin datos', ['device' => $device]);
        return null;
    }

    public function listarDispositivos(): array
    {
        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -2y)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh")
  |> distinct(column: "name")
  |> keep(columns: ["name"])
FLUX;

        $dispositivos = [];
        foreach ($this->query($flux) as $row) {
            if (!empty($row['name'])) {
                $dispositivos[] = $row['name'];
            }
        }

        Log::info('[InfluxService] listarDispositivos: ' . count($dispositivos) . ' dispositivos encontrados');
        return $dispositivos;
    }

    // ---------------------------------------------------------
    //  PRIVATE HELPERS
    // ---------------------------------------------------------

    private function query(string $flux, int $timeout = 30, int $maxRetries = 2): array
    {
        $headers = [
            'Authorization' => "Token {$this->influxToken}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ];
        $body = [
            'query'   => $flux,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $response = Http::withHeaders($headers)->timeout($timeout)->post($this->influxUrl, $body);

            if ($response->successful()) {
                return $this->parseCsv($response->body());
            }

            Log::warning('[InfluxService] query error', [
                'status'  => $response->status(),
                'attempt' => $attempt,
                'body'    => substr($response->body(), 0, 300),
            ]);

            if ($attempt < $maxRetries) {
                usleep(300_000 * $attempt);
            }
        }

        Log::error('[InfluxService] query falló tras reintentos', [
            'query_preview' => substr($flux, 0, 200),
        ]);
        return [];
    }

    private function parseCsv(string $csv): array
    {
        $rows    = [];
        $headers = null;

        foreach (explode("\n", $csv) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;

            $cols = str_getcsv($line);

            if ($headers === null) {
                $headers = $cols;
                continue;
            }

            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $cols[$i] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function rowsToMap(array $rows, string $timeCol, string $valueCol): array
    {
        $map = [];
        foreach ($rows as $row) {
            if (!isset($row[$timeCol], $row[$valueCol]) || !is_numeric($row[$valueCol])) continue;
            $t = $this->normalizeTimestamp($row[$timeCol]);
            if ($t) $map[$t] = (float) $row[$valueCol];
        }
        ksort($map);
        return $map;
    }

    private function normalizeTimestamp(string $t): ?string
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

    private function sanitizeTag(string $tag): string
    {
        return str_replace(['"', "\n", "\r", '\\'], '', $tag);
    }

    private function fluxTimeLiteral(string $date, bool $startOfDay = true): string
    {
        if (strlen($date) > 10) {
            try {
                return Carbon::parse($date)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $e) {
                // falls through to date-only parsing
            }
        }

        try {
            $dt = Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'));
        } catch (\Throwable $e) {
            $dt = Carbon::parse($date);
        }

        $dt = $startOfDay ? $dt->startOfDay() : $dt->endOfDay();
        return $dt->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
