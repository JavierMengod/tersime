<?php

namespace App\Services;

use App\Models\Ajuste;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InfluxService
{
    private string $bucket;
    private string $urlInflux;
    private string $tokenInflux;

    public function __construct()
    {
        $this->bucket      = Ajuste::get('influxdb_bucket') ?: config('tersime.influxdb.bucket', 'PINZAS');
        $this->urlInflux   = rtrim(Ajuste::get('influxdb_url') ?: config('tersime.influxdb.url', 'http://localhost:8086'), '/')
            . '/api/v2/query?org=' . (Ajuste::get('influxdb_org') ?: config('tersime.influxdb.org', 'tersime'));
        $this->tokenInflux = Ajuste::get('influxdb_token') ?: config('tersime.influxdb.token', '');

        if (empty($this->tokenInflux)) {
            Log::warning('[InfluxService] influxdb_token no configurado — las queries fallarán con HTTP 401');
        }
        if (empty($this->bucket)) {
            Log::warning('[InfluxService] influxdb_bucket no configurado');
        }
    }

    public function consumoTotal(string $etiqueta, string $inicio, string $fin): float
    {
        $etiqueta   = $this->limpiarEtiqueta($etiqueta);
        $inicioFlux = $this->fluxLiteralFecha($inicio, true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) =>
        r._measurement == "hourly" and
        r._field == "kwh" and
        r.name == "{$etiqueta}"
     )
  |> group()
  |> sum(column: "_value")
FLUX;

        foreach ($this->consultar($flux) as $fila) {
            if (isset($fila['_value']) && is_numeric($fila['_value'])) {
                return (float) $fila['_value'];
            }
        }

        return 0.0;
    }

    public function datosHorarios(string $etiqueta, string $inicio, string $fin): array
    {
        $etiqueta   = $this->limpiarEtiqueta($etiqueta);
        $inicioFlux = $this->fluxLiteralFecha($inicio, true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$etiqueta}")
  |> sort(columns: ["_time"])
FLUX;

        return $this->filasAMapa($this->consultar($flux), '_time', '_value');
    }

    public function datosDiarios(string $etiqueta, string $inicio, string $fin): array
    {
        $etiqueta   = $this->limpiarEtiqueta($etiqueta);
        $inicioFlux = $this->fluxLiteralFecha($inicio, true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$etiqueta}")
  |> aggregateWindow(every: 1d, fn: sum)
  |> keep(columns: ["_time", "_value"])
  |> sort(columns: ["_time"])
FLUX;

        return $this->filasAMapa($this->consultar($flux), '_time', '_value');
    }

    public function datosEstadisticos(string $etiqueta, string $inicio, string $fin): array
    {
        $etiqueta   = $this->limpiarEtiqueta($etiqueta);
        $inicioFlux = $this->fluxLiteralFecha($inicio, true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
import "math"

data = from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) =>
       r._measurement == "hourly" and
       r._field == "kwh" and
       r.name == "{$etiqueta}"
  )
  |> group()

data
  |> reduce(
      identity: {n: 0.0, sum: 0.0, min: 1000000000000000.0, max: -1000000000000000.0, sumSq: 0.0},
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
      max:    if r.max > -1000000000000000.0 then r.max else 0.0,
      min:    if r.min <  1000000000000000.0 then r.min else 0.0,
      sum:    r.sum
  }))
  |> keep(columns: ["mean", "stddev", "max", "min", "sum"])
FLUX;

        $resultado = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];
        foreach ($this->consultar($flux) as $fila) {
            foreach (['mean', 'stddev', 'max', 'min', 'sum'] as $clave) {
                if (isset($fila[$clave]) && is_numeric($fila[$clave])) {
                    $resultado[$clave] = (float) $fila[$clave];
                }
            }
            break;
        }

        return $resultado;
    }

    public function resumen(string $etiqueta, string $inicio, string $fin): array
    {
        try {
            $horas = $this->datosHorarios($etiqueta, $inicio, $fin);

            $total = array_sum($horas);

            $tz   = config('app.timezone', 'Europe/Madrid');
            $dias = [];
            foreach ($horas as $ts => $kwh) {
                $dia        = Carbon::parse($ts)->setTimezone($tz)->startOfDay()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
                $dias[$dia] = ($dias[$dia] ?? 0.0) + (float) $kwh;
            }
            ksort($dias);

            return ['total' => $total, 'horas' => $horas, 'dias' => $dias];
        } catch (\Throwable $e) {
            Log::error('[InfluxService] resumen ERROR', ['error' => $e->getMessage()]);
            return ['total' => 0, 'horas' => [], 'dias' => []];
        }
    }

    public function mediaHistoricaPeriodo(string $etiqueta, string $inicio, string $fin): ?float
    {
        try {
            $inicioDt    = Carbon::createFromFormat('Y-m-d', $inicio)->startOfDay();
            $finDt       = Carbon::createFromFormat('Y-m-d', $fin)->endOfDay();
            $segundosDiff = $finDt->timestamp - $inicioDt->timestamp;

            if ($segundosDiff <= 0) return null;

            $histFin    = $inicioDt->copy()->subSecond();
            $histInicio = $histFin->copy()->subSeconds($segundosDiff);

            return $this->consumoTotal($etiqueta, $histInicio->format('Y-m-d'), $histFin->format('Y-m-d'));
        } catch (\Throwable $e) {
            Log::error('[InfluxService] mediaHistoricaPeriodo ERROR', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function factorCarga(string $etiqueta = 'general', ?string $inicio = null, ?string $fin = null): ?float
    {
        $etiqueta = $this->limpiarEtiqueta($etiqueta);
        if (empty($etiqueta)) {
            Log::error('[InfluxService] factorCarga: parámetro etiqueta vacío');
            return null;
        }

        $inicio     = $inicio ?? Carbon::now()->subDays(365)->format('Y-m-d');
        $fin        = $fin    ?? Carbon::now()->format('Y-m-d');
        $inicioFlux = $this->fluxLiteralFecha($inicio, true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
hourly = from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) =>
      r._measurement == "hourly" and r._field == "kwh" and r.name == "{$etiqueta}"
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

        foreach ($this->consultar($flux) as $fila) {
            if (isset($fila['_value']) && is_numeric($fila['_value'])) {
                $v = (float) $fila['_value'];
                return $v > 0 ? $v : null;
            }
        }

        Log::info('[InfluxService] factorCarga sin resultado (sin datos en el período)', [
            'etiqueta' => $etiqueta,
            'inicio'   => $inicio,
            'fin'      => $fin,
        ]);
        return null;
    }

    public function mediaPorHora(string $etiqueta, string $inicio, string $fin): array
    {
        $etiqueta = $this->limpiarEtiqueta($etiqueta);
        try {
            $tz         = config('app.timezone', 'Europe/Madrid');
            $fechaBase  = '2025-01-01';
            $inicioFlux = $this->fluxLiteralFecha($inicio, true);
            $finFlux    = $this->fluxLiteralFecha($fin, false);

            $flux = <<<FLUX
import "date"
option timezone = "{$tz}"

from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) =>
       r._measurement == "hourly" and
       r._field == "kwh" and
       r["name"] == "{$etiqueta}"
  )
  |> map(fn: (r) => ({ r with hour: date.hour(t: r._time) }))
  |> group(columns: ["hour"])
  |> mean(column: "_value")
  |> keep(columns: ["hour", "_value"])
  |> map(fn: (r) => ({ r with hourStr: if r.hour < 10 then "0" + string(v: r.hour) else string(v: r.hour) }))
  |> map(fn: (r) => ({ r with _time: time(v: "{$fechaBase}T" + r.hourStr + ":00:00+01:00") }))
  |> keep(columns: ["_time", "_value"])
  |> sort(columns: ["_time"])
FLUX;

            $resultado = [];
            foreach ($this->consultar($flux) as $fila) {
                if (!isset($fila['_time'], $fila['_value']) || !is_numeric($fila['_value'])) continue;
                try {
                    $hora             = Carbon::parse($fila['_time'])->format('H');
                    $resultado[$hora] = (float) $fila['_value'];
                } catch (\Throwable $e) {
                    Log::warning('[InfluxService] mediaPorHora: timestamp inválido', [
                        'etiqueta' => $etiqueta,
                        'ts'       => $fila['_time'],
                    ]);
                }
            }

            return $resultado;

        } catch (\Throwable $e) {
            Log::error('[InfluxService] mediaPorHora ERROR', [
                'etiqueta' => $etiqueta,
                'inicio'   => $inicio,
                'fin'      => $fin,
                'error'    => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function datosParaPrediccion(string $etiqueta, string $fin): array
    {
        $etiqueta   = $this->limpiarEtiqueta($etiqueta);
        $inicioFlux = $this->fluxLiteralFecha(Carbon::parse($fin)->subYear()->format('Y-m-d'), true);
        $finFlux    = $this->fluxLiteralFecha($fin, false);

        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: {$inicioFlux}, stop: {$finFlux})
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$etiqueta}")
  |> sort(columns: ["_time"])
  |> keep(columns: ["_time", "_value"])
FLUX;

        $filas = $this->consultar($flux, 30);

        $timestamps = [];
        $values     = [];

        foreach ($filas as $fila) {
            if (!isset($fila['_time'], $fila['_value']) || !is_numeric($fila['_value'])) continue;
            $timestamps[] = $fila['_time'];
            $values[]     = (float) $fila['_value'];
        }

        Log::info('[InfluxService] datosParaPrediccion OK', [
            'etiqueta' => $etiqueta,
            'puntos'   => count($timestamps),
        ]);

        return ['timestamps' => $timestamps, 'values' => $values];
    }

    public function ultimoValor(string $etiqueta): ?float
    {
        $etiqueta = $this->limpiarEtiqueta($etiqueta);
        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -24h)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh" and r.name == "{$etiqueta}")
  |> last()
  |> keep(columns: ["_value"])
FLUX;

        foreach ($this->consultar($flux, 10) as $fila) {
            if (isset($fila['_value']) && is_numeric($fila['_value'])) {
                return (float) $fila['_value'];
            }
        }

        Log::warning('[InfluxService] ultimoValor: sin datos', ['etiqueta' => $etiqueta]);
        return null;
    }

    public function listarDispositivos(): array
    {
        // group() consolida en una sola tabla; distinct(column:"name") devuelve
        // un registro por dispositivo con el nombre en _value.
        $flux = <<<FLUX
from(bucket: "{$this->bucket}")
  |> range(start: -2y)
  |> filter(fn: (r) => r._measurement == "hourly" and r._field == "kwh")
  |> group()
  |> distinct(column: "name")
FLUX;

        $dispositivos = [];
        foreach ($this->consultar($flux) as $fila) {
            $val = $fila['_value'] ?? $fila['name'] ?? null;
            if (!empty($val) && $val !== '_value' && $val !== 'name') {
                $dispositivos[] = $val;
            }
        }

        Log::info('[InfluxService] listarDispositivos: ' . count($dispositivos) . ' dispositivos encontrados');
        return $dispositivos;
    }

    private function consultar(string $flux, int $timeout = 30, int $maxReintentos = 2): array
    {
        $encabezados = [
            'Authorization' => "Token {$this->tokenInflux}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/csv',
        ];
        $cuerpo = [
            'query'   => $flux,
            'dialect' => ['header' => true, 'delimiter' => ','],
        ];

        for ($intento = 1; $intento <= $maxReintentos; $intento++) {
            $respuesta = Http::withHeaders($encabezados)->timeout($timeout)->post($this->urlInflux, $cuerpo);

            if ($respuesta->successful()) {
                return $this->parsearCsv($respuesta->body());
            }

            Log::warning('[InfluxService] query error', [
                'status'  => $respuesta->status(),
                'intento' => $intento,
                'body'    => substr($respuesta->body(), 0, 300),
            ]);

            if ($intento < $maxReintentos) {
                usleep(300_000 * $intento);
            }
        }

        Log::error('[InfluxService] query falló tras reintentos', [
            'query_preview' => substr($flux, 0, 200),
        ]);
        return [];
    }

    private function parsearCsv(string $csv): array
    {
        $filas       = [];
        $encabezados = null;

        foreach (explode("\n", $csv) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || strpos($linea, '#') === 0) continue;

            $columnas = str_getcsv($linea, ',', '"', '\\');

            if ($encabezados === null) {
                $encabezados = $columnas;
                continue;
            }

            $fila = [];
            foreach ($encabezados as $i => $columna) {
                $fila[$columna] = $columnas[$i] ?? null;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    private function filasAMapa(array $filas, string $columnaFecha, string $columnaValor): array
    {
        $mapa = [];
        foreach ($filas as $fila) {
            if (!isset($fila[$columnaFecha], $fila[$columnaValor]) || !is_numeric($fila[$columnaValor])) continue;
            $t = $this->normalizarTimestamp($fila[$columnaFecha]);
            if ($t) $mapa[$t] = (float) $fila[$columnaValor];
        }
        ksort($mapa);
        return $mapa;
    }

    private function normalizarTimestamp(string $t): ?string
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

    private function limpiarEtiqueta(string $etiqueta): string
    {
        return str_replace(['"', "\n", "\r", '\\'], '', $etiqueta);
    }

    private function fluxLiteralFecha(string $fecha, bool $inicioDia = true): string
    {
        if (strlen($fecha) > 10) {
            try {
                return Carbon::parse($fecha)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            } catch (\Throwable $e) {
                // continúa con el parsing solo de fecha
            }
        }

        try {
            $dt = Carbon::createFromFormat('Y-m-d', $fecha, config('app.timezone'));
        } catch (\Throwable $e) {
            $dt = Carbon::parse($fecha);
        }

        $dt = $inicioDia ? $dt->startOfDay() : $dt->endOfDay();
        return $dt->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
