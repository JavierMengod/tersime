<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfluxController
{
    private string $bucket;
    private string $influxUrl;
    private string $influxToken;

    public function __construct()
    {
        $this->bucket      = Setting::get('influxdb_bucket') ?: config('app.influx_bucket', 'PINZAS');
        $this->influxUrl   = rtrim(Setting::get('influxdb_url') ?: env('INFLUXDB_URL', 'http://localhost:8086'), '/')
            . '/api/v2/query?org=' . (Setting::get('influxdb_org') ?: env('INFLUXDB_ORG', 'tersime'));
        $this->influxToken = Setting::get('influxdb_token') ?: env('INFLUXDB_TOKEN', '');
    }

    // ---------------------------------------------------------
    //  PUBLIC API
    // ---------------------------------------------------------

    public function consumoTotal(string $device, string $start, string $stop): float
    {
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
        $startFlux  = $this->fluxTimeLiteral($start, true);
        $stopFlux   = $this->fluxTimeLiteral($stop, false);
        $baseFilter = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$startFlux}, stop: {$stopFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$device}")
FLUX;

        $result = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];

        foreach (array_keys($result) as $fn) {
            $q    = $baseFilter . "\n  |> {$fn}(column: \"_value\") |> keep(columns:[\"_value\"]) |> limit(n:1)";
            $rows = $this->query($q);
            foreach ($rows as $row) {
                foreach ($row as $v) {
                    if (is_numeric($v)) {
                        $result[$fn] = (float) $v;
                        break 2;
                    }
                }
            }
        }

        return $result;
    }

    public function resumen(string $device, string $start, string $stop): array
    {
        try {
            return [
                'total' => $this->consumoTotal($device, $start, $stop),
                'horas' => $this->datosHorarios($device, $start, $stop),
                'dias'  => $this->datosDiarios($device, $start, $stop),
            ];
        } catch (\Throwable $e) {
            Log::error('[InfluxController] resumen ERROR', ['error' => $e->getMessage()]);
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
            Log::error('[InfluxController] mediaHistoricaPeriodo ERROR', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function factorCarga(string $device = 'general', string $start = null, string $stop = null): ?float
    {
        if (empty($device)) {
            Log::error('[InfluxController] factorCarga: parámetro device vacío');
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

        return null;
    }

    public function mediaPorHora(string $device, string $start, string $stop): array
    {
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
                    $h          = Carbon::parse($row['_time'])->setTimezone('UTC')->format('H');
                    $result[$h] = (float) $row['_value'];
                } catch (\Throwable $e) {
                    Log::warning('[InfluxController] mediaPorHora: timestamp inválido', [
                        'device' => $device,
                        'ts'     => $row['_time'],
                    ]);
                }
            }

            Log::debug("[InfluxController][mediaPorHora][{$device}] " . json_encode($result));
            return $result;

        } catch (\Throwable $e) {
            Log::error('[InfluxController] mediaPorHora ERROR', [
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

        Log::info('[InfluxController] datosParaPrediccion OK', [
            'device' => $device,
            'puntos' => count($timestamps),
        ]);

        return ['timestamps' => $timestamps, 'values' => $values];
    }

    public function ultimoValor(string $device): ?float
    {
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

        Log::warning('[InfluxController] ultimoValor: sin datos', ['device' => $device]);
        return null;
    }

    public function listarDispositivos(): array
    {
        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -2y)
  |> filter(fn: (r) => r._measurement == "daily" and r._field == "kwh_total")
  |> distinct(column: "name")
  |> keep(columns: ["name"])
FLUX;

        $dispositivos = [];
        foreach ($this->query($flux) as $row) {
            if (!empty($row['name'])) {
                $dispositivos[] = $row['name'];
            }
        }

        $dispositivos = array_values(array_unique($dispositivos));
        Log::info('[InfluxController] listarDispositivos:', $dispositivos);
        return $dispositivos;
    }

    // ---------------------------------------------------------
    //  PRIVATE HELPERS
    // ---------------------------------------------------------

    private function query(string $flux, int $timeout = 30): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Token {$this->influxToken}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ])->timeout($timeout)->post($this->influxUrl, [
            'query'   => $flux,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ]);

        if (!$response->successful()) {
            Log::error('[InfluxController] query error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);
            return [];
        }

        return $this->parseCsv($response->body());
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
}
