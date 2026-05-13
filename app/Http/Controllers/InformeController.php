<?php

namespace App\Http\Controllers;

use App\Models\Informe;
use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\InfluxController;
use App\Http\Controllers\OpenRouterController;

class InformeController extends Controller
{
    protected InfluxController $influx;

    public function __construct(InfluxController $influx, OpenRouterController $modeloLenguaje)
    {
        $this->influx = $influx;
        $this->modeloLenguaje = $modeloLenguaje; 
    }

    public function programados()
    {
        $informes = auth()->user()->programacionInformes ?? collect();
        return view('informes.programados', compact('informes'));
    }

    public function registro()
    {
        $registros = auth()->user()->informes ?? collect();
        return view('informes.registro', compact('registros'));
    }

    public function demanda()
    {
        $dispositivos = auth()->user()->dispositivos()->get() ?? collect();
        return view('informes.demanda', compact('dispositivos'));
    }

    public function generarInformeDemanda(Request $request)
    {
        $validated = $request->validate([
            'fromDate' => 'required|date_format:Y-m-d',
            'toDate' => 'required|date_format:Y-m-d|after_or_equal:fromDate',
            'email' => 'nullable|email',
            'dispositivos' => 'nullable|string',
            'notificaciones' => 'nullable|string',
        ]);

        Log::info("Inicio generación informe bajo demanda", ['user_id' => auth()->id()]);

        try {
            $dispositivosIds = [];
            $dispositivosPayload = [];
            if (!empty($validated['dispositivos'])) {
                $decoded = json_decode($validated['dispositivos'], true);
                if (is_array($decoded)) {
                    $dispositivosIds = array_column($decoded, 'id');
                    $dispositivosPayload = $decoded;
                }
            }

            $dispositivos = $dispositivosIds ? Dispositivo::whereIn('id', $dispositivosIds)->get() : collect();

            $notificaciones = [];
            if (!empty($validated['notificaciones'])) {
                $tmp = json_decode($validated['notificaciones'], true);
                if (is_array($tmp)) {
                    $notificaciones = $tmp;
                }
            }

            $fromDate = Carbon::parse($validated['fromDate'])->format('Y-m-d');
            $toDate = Carbon::parse($validated['toDate'])->format('Y-m-d');

            $resumenPorDispositivo = [];
            $totalesGlobales = 0.0;

            foreach ($dispositivos as $dispositivo) {
                try {
                    $deviceName = $dispositivo->URL ?? $dispositivo->nombre ?? "device_{$dispositivo->id}";

                    $res = ['total' => 0.0, 'horas' => [], 'dias' => []];
                    try {
                        $res = $this->influx->resumen($deviceName, $fromDate, $toDate);
                        if (!is_array($res)) {
                            $res = ['total' => 0.0, 'horas' => [], 'dias' => []];
                        }
                    } catch (\Throwable $e) {
                        Log::error('[Informe] Error llamando a Influx->resumen', [
                            'device' => $deviceName,
                            'error' => $e->getMessage()
                        ]);
                    }

                    $horas = $res['horas'] ?? [];
                    $total = isset($res['total']) ? (float) $res['total'] : 0.0;
                    $dias = $res['dias'] ?? [];

                    $stats = [
                        'mean' => null,
                        'stddev' => null,
                        'max' => null,
                        'min' => null,
                        'sum' => null,
                    ];

                    try {
                        $remoteStats = $this->influx->datosEstadisticos($deviceName, $fromDate, $toDate);
                        if (is_array($remoteStats)) {
                            $stats = array_merge($stats, $remoteStats);
                        }
                    } catch (\Throwable $eStats) {
                        Log::warning('[Informe] datosEstadisticos fallo', [
                            'device' => $deviceName,
                            'error' => $eStats->getMessage()
                        ]);
                    }

                    $historicalTotal = null;
                    $factor_carga = null;

                    try {
                        $factor_carga = $this->influx->factorCarga($deviceName, $fromDate, $toDate);
                    } catch (\Throwable $eFc) {
                        Log::warning('[Informe] factorCarga fallo', [
                            'device' => $deviceName,
                            'error' => $eFc->getMessage()
                        ]);
                    }

                    try {
                        $historicalTotal = $this->influx->mediaHistoricaPeriodo($deviceName, $fromDate, $toDate);
                    } catch (\Throwable $eHist) {
                        Log::warning('[Informe] No se pudo obtener mediaHistoricaPeriodo', [
                            'device' => $deviceName,
                            'error' => $eHist->getMessage()
                        ]);
                    }

                    $variationPercent = null;
                    if (!is_null($historicalTotal) && $historicalTotal != 0.0) {
                        $variationPercent = (($total - $historicalTotal) / $historicalTotal) * 100.0;
                    }

                    $resumenPorDispositivo[] = [
                        'id' => $dispositivo->id,
                        'nombre' => $dispositivo->nombre ?? $deviceName,
                        'device_key' => $deviceName,
                        'total_kwh' => round($total, 4),
                        'mean_kwh_h' => isset($stats['mean']) && is_numeric($stats['mean']) ? round($stats['mean'], 4) : null,
                        'stddev' => isset($stats['stddev']) && is_numeric($stats['stddev']) ? round($stats['stddev'], 4) : null,
                        'max' => isset($stats['max']) && is_numeric($stats['max']) ? round($stats['max'], 4) : null,
                        'min' => isset($stats['min']) && is_numeric($stats['min']) ? round($stats['min'], 4) : null,
                        'factor_carga' => is_null($factor_carga) ? null : round($factor_carga, 6),
                        'historical_total' => is_null($historicalTotal) ? null : round($historicalTotal, 4),
                        'variation_percent' => is_null($variationPercent) ? null : round($variationPercent, 2),
                        'horas' => $horas,
                        'dias' => $dias,
                    ];

                    $totalesGlobales += (float) $total;
                } catch (\Throwable $eDev) {
                    Log::error('[Informe] Error procesando dispositivo', [
                        'dispositivo_id' => $dispositivo->id ?? null,
                        'error' => $eDev->getMessage()
                    ]);
                    $resumenPorDispositivo[] = [
                        'id' => $dispositivo->id ?? null,
                        'nombre' => $dispositivo->nombre ?? 'Desconocido',
                        'device_key' => $dispositivo->URL ?? null,
                        'error' => true,
                        'error_message' => $eDev->getMessage()
                    ];
                }
            }

            foreach ($resumenPorDispositivo as &$row) {
                if (isset($row['total_kwh']) && $totalesGlobales > 0) {
                    $row['pct_over_total'] = round(($row['total_kwh'] / $totalesGlobales) * 100.0, 2);
                } else {
                    $row['pct_over_total'] = 0.0;
                }
            }
            unset($row);

            $dispositivosQuery = $this->buildDispositivosQuery($dispositivos);
            $grafanaBase = config('app.grafana_base_url', 'http://155.210.71.113:3000');
            $fromMillis = Carbon::parse($fromDate)->startOfDay()->timestamp * 1000;
            $toMillis = Carbon::parse($toDate)->endOfDay()->timestamp * 1000;
            $fechaInicio = $this->epochToIso8601($fromMillis);
            $fechaFin = $this->epochToIso8601($toMillis);
            $fechaFinPrevisionMillis = Carbon::parse($toDate)->endOfDay()->addWeek()->timestamp * 1000;

            $panelUrlTendencia =
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&from={$fromMillis}&to={$toMillis}&timezone=browser{$dispositivosQuery}&theme=light&panelId=panel-1";

            $graficas = [];
            $datos = [];
            foreach ($dispositivos as $dispositivo) {
                $url = $dispositivo->URL;
                $panelMediaHoraria =
                    "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                    . "?orgId=1&var-start={$fechaInicio}&var-end={$fechaFin}&from=1735689600000&to=1735775999000&timezone=browser&var-dispositivos=$url&theme=light&panelId=panel-5";
                $panelMediaHorariaHistorico =
                    "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                    . "?orgId=1&var-start=2024-09-01T00:00:00Z&var-end={$fechaFin}&from=1735689600000&to=1735775999000&timezone=browser&var-dispositivos=$url&theme=light&panelId=panel-5";
                //$panelPrevisionHoraria = "http://155.210.71.113:3000/d-solo/eegznxsjl47i8b/dashboard-initiot?orgId=1&var-start=2025-11-11T00:00:00Z&var-end=2025-11-18T23:59:59Z&from=1735689600000&to=1735775999000&timezone=browser&var-dispositivos=cabras&theme=light&panelId=panel-4";
                    //"{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                    //. "?orgId=1&var-start={$fechaInicio}&var-end={$fechaFin}&from={$fromMillis}&to={$fechaFinPrevisionMillis}&var-predict_hours=180&timezone=browser&var-dispositivos=$url&theme=light&panelId=panel-4";
                
                $graficas[$dispositivo->URL] = [
                    'media-horaria' => $this->descargarGrafanaRenderer($panelMediaHoraria, "media-horaria-{$dispositivo->URL}"),
                    'media-horaria-historico' => $this->descargarGrafanaRenderer($panelMediaHorariaHistorico, "media-horaria-historico-{$dispositivo->URL}")
                    //'prevision' => $this->descargarGrafanaRenderer($panelPrevisionHoraria, "prevision-horaria-{$dispositivo->URL}")
                ];
                $start = Carbon::parse($fechaInicio)->toDateString(); // "Y-m-d"
                $end   = Carbon::parse($fechaFin)->toDateString();
                $datos[$dispositivo->URL] = [
                    'media-horaria' => $this->influx->mediaPorHora($url, $start, $end),
                    'media-horaria-historico' => $this->influx->mediaPorHora($url, '2025-01-01', $end),
                    'bruto-dispositivo' => $this->influx->datosHorarios($url, $fechaInicio, $fechaFin),
                    'nombre-dispositivo' => $dispositivo->nombre
                ];

                Log::info("Panel Media Horaria ({$dispositivo->URL}): {$panelMediaHoraria}");
                //Log::info("Panel Media Horaria Histórico ({$dispositivo->URL}): {$panelMediaHorariaHistorico}");
                //Log::info("Datos del dispositivo {$dispositivo->URL}: " . json_encode($datos[$dispositivo->URL], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                    
            }

            $graficas['tiempo-real'] = $this->descargarGrafanaRenderer($panelUrlTendencia, "tiempo-real");

            $anomalias = $this->obtenerAnomalias($dispositivos, $fromDate, $toDate);

            $costeEstimado = $this->obtenerCosteEstimado($dispositivos, $fechaInicio, $fechaFin);

            Log::info("Comienzo de LLM");
            $resumen = $this->modeloLenguaje->resumen($datos, $anomalias, $costeEstimado, $resumenPorDispositivo);
            $conclusion = $this->modeloLenguaje->conclusion($datos, $anomalias, $costeEstimado, $resumenPorDispositivo);
            $distribucionHorariaTextual = $this->modeloLenguaje->distribucionHorariaTextual($datos, $anomalias, $costeEstimado, $resumenPorDispositivo);
            Log::info("Fin de LLM");
            $data = [
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'dispositivos' => $dispositivos,
                'email' => $validated['email'] ?? null,
                'notificaciones' => $notificaciones,
                'user' => auth()->user(),
                'resumen' => $resumen,
                'resumenPorDispositivo' => $resumenPorDispositivo,
                'tablaResumen' => null,
                'tablaAnomalias' => $anomalias,
                'costeEstimado' => $costeEstimado,
                'conclusiones' => null,
                'graficas' => $graficas,
                'conclusion' => $conclusion,
                'distribucionHorariaTextual' => $distribucionHorariaTextual,
            ];

            $filename = 'informe_' . auth()->id() . '_' . now()->format('Ymd_His') . '.pdf';
            $storagePath = 'public/informes/' . $filename;

            $pdf = PDF::loadView('informes.informe_bajo_demanda', $data)
                ->setOption('enable-local-file-access', true)
                ->setOption('javascript-delay', 500)
                ->setOption('no-stop-slow-scripts', true)
                ->setOption('disable-smart-shrinking', false)
                ->setOption('viewport-size', '1280x1024');

            Storage::put($storagePath, $pdf->output());

            $fileSize = 0;
            try {
                $fileSize = Storage::size($storagePath);
            } catch (\Throwable $eSize) {
                Log::warning('[PDF] No se pudo obtener tamaño del PDF', ['error' => $eSize->getMessage()]);
            }

            $informe = Informe::create([
                'user_id' => auth()->id(),
                'tipo' => 'Demanda',
                'nombre_archivo' => $filename,
                'pdf_path' => $storagePath,
                'periodo_from' => $fromDate,
                'periodo_to' => $toDate,
                'size_bytes' => $fileSize,
                'generated_at' => now(),
                'telegram' => in_array('telegram', $notificaciones),
                'discord' => in_array('discord', $notificaciones),
                'correo' => in_array('correo', $notificaciones),
                'correo_destino' => $validated['email'] ?? null,
                'activo' => false,
            ]);

            if (!empty($dispositivosIds)) {
                $informe->dispositivos()->sync($dispositivosIds);
            }

            return response()->json([
                'success' => true,
                'download_url' => route('informes.demanda.descargar', ['filename' => $filename]),
            ]);

        } catch (\Throwable $e) {

            Log::error("Error generando informe bajo demanda: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el informe',
            ], 500);
        }
    }


    private function buildDispositivosQuery($dispositivos): string
    {
        $query = '';
        foreach ($dispositivos as $dispositivo) {
            if (!empty($dispositivo->URL)) {
                $query .= "&var-dispositivos=" . urlencode($dispositivo->URL);
            } else {
                $query .= "&var-dispositivos=" . urlencode($dispositivo->nombre ?? "device_{$dispositivo->id}");
            }
        }

        return $query;
    }

    /**
     * Convierte epoch (segundos o milisegundos) a una fecha/hora ISO8601.
     *
     * @param int|float $epoch       Timestamp en segundos o milisegundos.
     * @param string    $timezone    Zona horaria (por defecto UTC).
     * @return string                Fecha formateada (ej: 2025-11-16T19:31:56Z)
     */
    private function epochToIso8601($epoch, $timezone = 'UTC')
    {
        // Si viene en milisegundos → convertir a segundos
        if ($epoch > 9999999999) {
            $epoch = $epoch / 1000;
        }

        return Carbon::createFromTimestamp($epoch, $timezone)
            ->toIso8601ZuluString(); // Ej: 2025-11-16T22:31:56+01:00
    }


    //////////////////////////////////////////////////////////////////////////
    //  >>>>>>> MÉTODO CORREGIDO — RENDERER SIEMPRE CON ?url=<PANEL> <<<<<<<<
    //////////////////////////////////////////////////////////////////////////
    private function descargarGrafanaRenderer(string $panelUrl, string $nombreArchivo)
    {
        try {
            $rendererBase = env('GRAFANA_RENDERER_URL', 'http://localhost:8081/render');
            $width = env('GRAFANA_RENDERER_WIDTH', 1000);
            $height = env('GRAFANA_RENDERER_HEIGHT', 500);
            $timeout = env('GRAFANA_RENDERER_TIMEOUT', 60);

            // Normalizamos base
            $rendererBase = rtrim($rendererBase, '/');

            // ✔️ Siempre usar modo: /render?url=<ENCODED>
            $rendererUrl =
                "{$rendererBase}?url=" . urlencode($panelUrl)
                . "&width={$width}&height={$height}&timeout={$timeout}";

            Log::info('[Renderer] Llamando a renderer', [
                'renderer_url' => $rendererUrl
            ]);

            $apiKey = env('GRAFANA_RENDERER_TOKEN');

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Accept' => 'image/png',
            ])
                ->withOptions(['verify' => false])
                ->timeout(90)
                ->get($rendererUrl);

            if ($response->successful()) {
                $path = "public/graficas/{$nombreArchivo}.png";
                Storage::put($path, $response->body());
                return storage_path("app/{$path}");
            }

            Log::error("Error en render de Grafana (renderer).", [
                'renderer_url' => $rendererUrl,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000),
            ]);

        } catch (\Throwable $e) {

            Log::error("Excepción al renderizar Grafana con renderer: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        return null;
    }



    public function update(Request $request, Informe $informe)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'dispositivos' => 'required|array|min:1',
            'dispositivos.*' => 'integer|exists:dispositivos,id',
            'periodicidad' => 'required|in:Diario,Semanal,Mensual',
            'telegram' => 'sometimes|boolean',
            'discord' => 'sometimes|boolean',
            'correo' => 'sometimes|boolean',
            'correo_destino' => 'nullable|email',
            'activo' => 'sometimes|boolean',
        ]);

        $informe->update([
            'nombre' => $data['nombre'],
            'periodicidad' => $data['periodicidad'],
            'telegram' => $request->has('telegram'),
            'discord' => $request->has('discord'),
            'correo' => $request->has('correo'),
            'correo_destino' => $data['correo_destino'] ?? null,
            'activo' => $request->has('activo'),
        ]);

        $informe->dispositivos()->sync($data['dispositivos']);

        return redirect()->route('informes-programados')
            ->with('success', 'Informe actualizado correctamente.');
    }



    public function destroy(Informe $informe)
    {
        $this->authorizeAccess($informe);

        if (!empty($informe->pdf_path)) {
            $relative = str_replace('public/', '', ltrim($informe->pdf_path, '/'));
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            } else {
                [$absolutePath] = $this->resolvePdfAbsolutePath($informe->pdf_path, $informe->nombre_archivo);
                if ($absolutePath && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }
        }

        $informe->delete();
        return back()->with('success', 'Registro eliminado correctamente.');
    }



    private function authorizeAccess(Informe $informe)
    {
        if ($informe->user_id !== auth()->id()) {
            abort(403, 'No tienes permiso para acceder a este recurso.');
        }
    }



    private function resolvePdfAbsolutePath(?string $pdfPath, ?string $downloadName): array
    {
        if (empty($pdfPath)) {
            return [null, $downloadName];
        }

        if (preg_match('/^(\/|[A-Za-z]:\\\\)/', $pdfPath) === 1) {
            return [$pdfPath, $downloadName];
        }

        $relative = ltrim($pdfPath, '/');
        $relative = preg_replace('#^storage/app/public/#', '', $relative);
        $relative = preg_replace('#^public/#', '', $relative);
        $relative = preg_replace('#^storage/#', '', $relative);

        $absolute = storage_path('app/public/' . $relative);
        return [$absolute, $downloadName];
    }



    public function download(Informe $informe)
    {
        $this->authorizeAccess($informe);
        [$absolutePath, $downloadName] = $this->resolvePdfAbsolutePath($informe->pdf_path, $informe->nombre_archivo);

        if (empty($absolutePath) || !is_file($absolutePath)) {
            return back()->with('error', "No se encontró el archivo en el servidor.");
        }

        return response()->download($absolutePath, $downloadName ?: basename($absolutePath), [
            'Content-Type' => 'application/pdf',
        ]);
    }


    /**
     * Detecta anomalías de consumo diario por dispositivo.
     * Devuelve un array con listas de anomalías por dispositivo.
     *
     * Cada anomalía incluirá:
     * - fecha
     * - valor_kwh
     * - media_historica_kwh
     * - exceso_kwh
     * - multiplicador_usado
     *
     * @param \Illuminate\Support\Collection $dispositivos
     * @param string $fromDate YYYY-MM-DD
     * @param string $toDate   YYYY-MM-DD
     * @param float $multiplicador Umbral de desviación (ej: 1.35 → 35% más de lo normal)
     *
     * @return array
     */
    private function obtenerAnomalias($dispositivos, string $fromDate, string $toDate): array
    {
        //\Log::debug("[ANOMALÍAS-HORA] Iniciando cálculo…");

        // Leer multiplicador del .env (por defecto 3.5)
        $multiplicador = (float) env('MULTIPLICADOR_ANOMALIAS', 3.5);
        //\Log::debug("[ANOMALÍAS-HORA] Multiplicador desde .env = {$multiplicador}");

        $anomalias = [];

        foreach ($dispositivos as $dispositivo) {

            // Normalizar clave
            if (is_string($dispositivo)) {
                $deviceKey = $dispositivo;
            } elseif (is_array($dispositivo)) {
                $deviceKey = $dispositivo['URL']
                    ?? $dispositivo['nombre']
                    ?? (string) ($dispositivo['id'] ?? 'desconocido');
            } elseif (is_object($dispositivo)) {
                $deviceKey = $dispositivo->URL
                    ?? $dispositivo->nombre
                    ?? "device_{$dispositivo->id}";
            } else {
                $deviceKey = (string) $dispositivo;
            }

            $deviceKey = (string) $deviceKey;
            //\Log::debug("[ANOMALÍAS-HORA][$deviceKey] Procesando dispositivo");

            // 1. Datos horarios reales del periodo (UTC)
            $horarios = $this->influx->datosHorarios($deviceKey, $fromDate, $toDate);
            //\Log::debug("[ANOMALÍAS-HORA][$deviceKey] Datos horarios: " . json_encode($horarios));

            if (empty($horarios)) {
                $anomalias[$deviceKey] = [];
                continue;
            }

            // Coger 2 años atrás para la media
            $fromDateHistorico = \Carbon\Carbon::parse($toDate)->copy()->subYears(2)->toDateString();

            // 2. Media histórica por hora 0–23
            $mediaPorHora = $this->influx->mediaPorHora($deviceKey, $fromDateHistorico, $toDate);
            //\Log::debug("[ANOMALÍAS-HORA][$deviceKey] Media histórica por hora: " . json_encode($mediaPorHora));

            if (empty($mediaPorHora)) {
                $anomalias[$deviceKey] = [];
                continue;
            }

            $lista = [];

            // 3. Comparación hora a hora
            foreach ($horarios as $fechaIso => $valorKwh) {

                $dt = \Carbon\Carbon::parse($fechaIso); // UTC
                $hora = $dt->format('H'); // 00–23

                if (!isset($mediaPorHora[$hora])) {
                    continue;
                }

                $mediaHora = (float) $mediaPorHora[$hora];
                $valor = (float) $valorKwh;

                // Umbrales alto y bajo
                $umbralAlto = $mediaHora * $multiplicador;
                $umbralBajo = ($multiplicador == 0 ? $mediaHora : $mediaHora / $multiplicador);

                // ------------------------------
                // ANOMALÍA POR EXCESO
                // ------------------------------
                if ($valor > $umbralAlto) {

                    $anomalia = [
                        'tipo' => 'exceso',
                        'device' => $deviceKey,
                        'fecha' => $fechaIso,
                        'hora' => $hora,
                        'valor_kwh' => round($valor, 6),
                        'media_historica_hora_kwh' => round($mediaHora, 6),
                        'diferencia_kwh' => round($valor - $mediaHora, 6),
                        'multiplicador_usado' => $multiplicador,
                        'mensaje' => 'Exceso anormal de consumo horario',
                    ];

                    $lista[] = $anomalia;
                    //\Log::debug("[ANOMALÍA-HORA-EXCESO][$deviceKey][$fechaIso] " . json_encode($anomalia));
                }

                // ------------------------------
                // ANOMALÍA POR DEFECTO
                // ------------------------------
                elseif ($valor < $umbralBajo) {

                    $anomalia = [
                        'tipo' => 'defecto',
                        'device' => $deviceKey,
                        'fecha' => $fechaIso,
                        'hora' => $hora,
                        'valor_kwh' => round($valor, 6),
                        'media_historica_hora_kwh' => round($mediaHora, 6),
                        'diferencia_kwh' => round($mediaHora - $valor, 6),
                        'multiplicador_usado' => $multiplicador,
                        'mensaje' => 'Consumo anormalmente bajo para esta hora',
                    ];

                    $lista[] = $anomalia;
                    //\Log::debug("[ANOMALÍA-HORA-DEFECTO][$deviceKey][$fechaIso] " . json_encode($anomalia));
                }
            }

            $anomalias[$deviceKey] = $lista;
        }

        //\Log::debug("[ANOMALÍAS-HORA] Resultado final: " . json_encode($anomalias));

        return $anomalias;
    }

    private function obtenerCosteEstimado($dispositivos, string $fromDate, string $toDate): array
    {
        $coste_estimado_kwh = (float) env('COSTE_ESTIMADO_KWH', 3.5);
        //\Log::debug("[COSTE-ESTIMADO] Coste por kWh desde .env = {$coste_estimado_kwh}");

        $resultados = [];

        foreach ($dispositivos as $dispositivo) {

            // Normalizar clave igual que en obtenerAnomalias()
            if (is_string($dispositivo)) {
                $deviceKey = $dispositivo;
            } elseif (is_array($dispositivo)) {
                $deviceKey = $dispositivo['URL']
                    ?? $dispositivo['nombre']
                    ?? (string) ($dispositivo['id'] ?? 'desconocido');
            } elseif (is_object($dispositivo)) {
                $deviceKey = $dispositivo->URL
                    ?? $dispositivo->nombre
                    ?? "device_{$dispositivo->id}";
            } else {
                $deviceKey = (string) $dispositivo;
            }

            $deviceKey = (string) $deviceKey;

            //\Log::debug("[COSTE-ESTIMADO][$deviceKey] Calculando consumo total…");

            // Obtener consumo total en el periodo
            $consumoTotal = $this->influx->consumoTotal($deviceKey, $fromDate, $toDate);
            //\Log::debug("[COSTE-ESTIMADO][$deviceKey] Consumo total kWh = {$consumoTotal}");

            // Calcular el coste estimado
            $coste = round($consumoTotal * $coste_estimado_kwh, 6);

            //\Log::debug("[COSTE-ESTIMADO][$deviceKey] Coste estimado = {$coste}");

            $resultados[$deviceKey] = [
                'consumo_total_kwh' => round($consumoTotal, 6),
                'coste_estimado' => $coste,
            ];
        }

        //\Log::debug("[COSTE-ESTIMADO] Resultado final: " . json_encode($resultados));

        return $resultados;
    }

    private function generarResumen(){

    }
}
