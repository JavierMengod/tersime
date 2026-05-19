<?php

namespace App\Services;

use App\Services\InfluxService;
use App\Services\OpenRouterService;
use App\Models\Informe;
use App\Models\Setting;
use App\Models\User;
use Mpdf\Mpdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InformeService
{
    protected InfluxService $influx;
    protected OpenRouterService $modeloLenguaje;

    public function __construct(InfluxService $influx, OpenRouterService $modeloLenguaje)
    {
        $this->influx         = $influx;
        $this->modeloLenguaje = $modeloLenguaje;
    }

    /**
     * Actualiza un Informe ya existente con el PDF generado.
     * Usado por GenerarInformeJob para el flujo asíncrono.
     */
    public function generarPdfParaInformeExistente(
        Informe $informe,
        User $user,
        \Illuminate\Support\Collection $dispositivos,
        string $fromDate,
        string $toDate,
        bool $telegram = false,
        bool $correo = false,
        bool $discord = false,
        ?string $correoDestino = null
    ): array {
        $data = $this->compilarGeneracion($user, $dispositivos, $fromDate, $toDate, $telegram, $correo, $discord);

        $informe->update([
            'nombre_archivo' => $data['filename'],
            'pdf_path'       => $data['storagePath'],
            'size_bytes'     => $data['fileSize'],
            'generated_at'   => now(),
        ]);

        $downloadUrl = route('informes.download', $informe->id, false);

        Log::info('[InformeService] PDF generado', ['filename' => $data['filename'], 'informe_id' => $informe->id]);

        return array_merge($data, compact('informe', 'downloadUrl'));
    }

    // ── Núcleo compartido ──────────────────────────────────────────────────────

    private function compilarGeneracion(
        User $user,
        \Illuminate\Support\Collection $dispositivos,
        string $fromDate,
        string $toDate,
        bool $telegram,
        bool $correo,
        bool $discord
    ): array {
        // Si fromDate/toDate incluyen hora (datetime), se respeta tal cual.
        // Si son solo fecha (Y-m-d), se aplica startOfDay/endOfDay para cubrir el día completo.
        $hasTime     = fn(string $d) => strlen($d) > 10;
        $fromMillis  = ($hasTime($fromDate) ? Carbon::parse($fromDate) : Carbon::parse($fromDate)->startOfDay())->timestamp * 1000;
        $toMillis    = ($hasTime($toDate)   ? Carbon::parse($toDate)   : Carbon::parse($toDate)->endOfDay())->timestamp * 1000;
        $fechaInicio = $this->epochToIso8601($fromMillis);
        $fechaFin    = $this->epochToIso8601($toMillis);
        $start       = Carbon::parse($fechaInicio)->toDateString();
        $end         = Carbon::parse($fechaFin)->toDateString();

        // ── 1. Datos por dispositivo (InfluxDB) ────────────────────────────
        $resumenPorDispositivo  = [];
        $totalesGlobales        = 0.0;
        $horariosPrefetchados   = [];
        $mediasHorariaHistorico = [];
        $horariosCache          = [];

        foreach ($dispositivos as $dispositivo) {
            try {
                $tag = $this->resolveTag($dispositivo);

                $res = ['total' => 0.0, 'horas' => [], 'dias' => []];
                try {
                    $fetched = $this->influx->resumen($tag, $fechaInicio, $fechaFin);
                    if (is_array($fetched)) {
                        $res = $fetched;
                    }
                } catch (\Throwable $e) {
                    Log::error('[InformeService] Influx->resumen falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                $total = isset($res['total']) ? (float) $res['total'] : 0.0;

                $stats = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];
                $horariosCache[$tag] = $res['horas'] ?? [];

                try {
                    $remoteStats = $this->influx->datosEstadisticos($tag, $fechaInicio, $fechaFin);
                    if (is_array($remoteStats)) {
                        $stats = array_merge($stats, $remoteStats);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] datosEstadisticos falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                $factorCarga     = null;
                $historicalTotal = null;

                try {
                    $factorCarga = $this->influx->factorCarga($tag, $fechaInicio, $fechaFin);
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] factorCarga falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                try {
                    $historicalTotal = $this->influx->mediaHistoricaPeriodo($tag, $start, $end);
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] mediaHistoricaPeriodo falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                $variationPercent = null;
                if (!is_null($historicalTotal) && $historicalTotal != 0.0) {
                    $variationPercent = (($total - $historicalTotal) / $historicalTotal) * 100.0;
                }

                $resumenPorDispositivo[] = [
                    'id'               => $dispositivo->id,
                    'nombre'           => $dispositivo->nombre ?? $tag,
                    'device_key'       => $tag,
                    'total_kwh'        => round($total, 4),
                    'mean_kwh_h'       => isset($stats['mean']) && is_numeric($stats['mean']) ? round($stats['mean'], 4) : null,
                    'stddev'           => isset($stats['stddev']) && is_numeric($stats['stddev']) ? round($stats['stddev'], 4) : null,
                    'max'              => isset($stats['max']) && is_numeric($stats['max']) ? round($stats['max'], 4) : null,
                    'min'              => isset($stats['min']) && is_numeric($stats['min']) ? round($stats['min'], 4) : null,
                    'factor_carga'     => is_null($factorCarga) ? null : round($factorCarga, 6),
                    'historical_total' => is_null($historicalTotal) ? null : round($historicalTotal, 4),
                    'variation_percent' => is_null($variationPercent) ? null : round($variationPercent, 2),
                    'horas'            => $res['horas'] ?? [],
                    'dias'             => $res['dias'] ?? [],
                ];

                $totalesGlobales += $total;
            } catch (\Throwable $e) {
                Log::error('[InformeService] Error procesando dispositivo', [
                    'dispositivo_id' => $dispositivo->id ?? null,
                    'error'          => $e->getMessage(),
                ]);
                $resumenPorDispositivo[] = [
                    'id'            => $dispositivo->id ?? null,
                    'nombre'        => $dispositivo->nombre ?? 'Desconocido',
                    'device_key'    => $this->resolveTag($dispositivo),
                    'error'         => true,
                    'error_message' => $e->getMessage(),
                ];
            }
        }

        foreach ($resumenPorDispositivo as &$row) {
            $row['pct_over_total'] = (isset($row['total_kwh']) && $totalesGlobales > 0)
                ? round(($row['total_kwh'] / $totalesGlobales) * 100.0, 2)
                : 0.0;
        }
        unset($row);

        // ── 1b. Métricas avanzadas (franjas, tendencia, picos) ─────────────
        $metricasAvanzadas = $this->computarMetricasAvanzadas($resumenPorDispositivo);

        // Liberar horas/dias — ya procesados, no se necesitan en la vista PDF
        foreach ($resumenPorDispositivo as &$row) {
            unset($row['horas'], $row['dias']);
        }
        unset($row);

        // ── 2. Gráficas + datos horarios ──────────────────────────────────
        $grafanaBase       = rtrim(config('tersime.grafana.renderer_base_url') ?: Setting::get('grafana_base_url') ?: 'http://grafana:3000', '/');
        $dispositivosQuery = $this->buildDispositivosQuery($dispositivos);

        $panelUrlTendencia =
            "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
            . "?orgId=1&from={$fromMillis}&to={$toMillis}&timezone=Europe%2FMadrid{$dispositivosQuery}&theme=light&panelId=panel-1";

        $graficas  = [];
        $datos     = [];
        $defaultTimeout = (int) config('tersime.grafana.renderer_timeout', 60);
        $panelUrls = [
            'tiempo-real'    => [$panelUrlTendencia, 'tiempo-real', 120],
            'consumo-diario' => [
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&from={$fromMillis}&to={$toMillis}&timezone=Europe%2FMadrid{$dispositivosQuery}&theme=light&panelId=panel-7",
                'consumo-diario',
                $defaultTimeout,
            ],
        ];

        // Panel-5 muestra la media horaria con un eje X fijo de 0-23h.
        // La fecha concreta no importa; solo necesita ser un día completo válido.
        $panel5RefDay = Carbon::create(2025, 1, 1, 0, 0, 0, 'Europe/Madrid');
        $panel5From   = $panel5RefDay->getTimestamp() * 1000;
        $panel5To     = $panel5RefDay->copy()->endOfDay()->getTimestamp() * 1000;

        foreach ($dispositivos as $dispositivo) {
            $tag             = $this->resolveTag($dispositivo);
            $historicalStart = Carbon::parse($end)->subYears(2)->toDateString();

            $panelUrls["media-horaria-{$tag}"] = [
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start={$fechaInicio}&var-end={$fechaFin}&var-dispositivos={$tag}&theme=light&panelId=panel-5&from={$panel5From}&to={$panel5To}&timezone=Europe%2FMadrid",
                "media-horaria-{$tag}",
                $defaultTimeout,
            ];
            $historicalStartIso = Carbon::parse($historicalStart)->toDateString() . 'T00:00:00Z';
            $panelUrls["media-horaria-historico-{$tag}"] = [
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start={$historicalStartIso}&var-end={$fechaFin}&var-dispositivos={$tag}&theme=light&panelId=panel-5&from={$panel5From}&to={$panel5To}&timezone=Europe%2FMadrid",
                "media-horaria-historico-{$tag}",
                120,
            ];

            $mediaHistorica = $this->influx->mediaPorHora($tag, $historicalStart, $end);

            $datos[$tag] = [
                'media-horaria'           => $this->influx->mediaPorHora($tag, $start, $end),
                'media-horaria-historico' => $mediaHistorica,
                'nombre-dispositivo'      => $dispositivo->nombre,
            ];

            // Reutiliza los horarios ya descargados en loop 1
            $horariosPrefetchados[$tag]   = $horariosCache[$tag] ?? [];
            $mediasHorariaHistorico[$tag] = $mediaHistorica;
        }

        $archivosGraficas = $this->renderizarGraficas($panelUrls, $user->name);

        foreach ($dispositivos as $dispositivo) {
            $tag = $this->resolveTag($dispositivo);
            $graficas[$tag] = [
                'media-horaria'           => $archivosGraficas["media-horaria-{$tag}"] ?? null,
                'media-horaria-historico' => $archivosGraficas["media-horaria-historico-{$tag}"] ?? null,
            ];
        }
        $graficas['tiempo-real']    = $archivosGraficas['tiempo-real']    ?? null;
        $graficas['consumo-diario'] = $archivosGraficas['consumo-diario'] ?? null;
        // Convertir rutas absolutas a file:// para que mPDF las lea del disco
        // directamente, evitando incrustar base64 en el HTML (causa pcre overflow).
        // Los ficheros se borran DESPUÉS de que mPDF haya escrito el PDF.
        $graficas = $this->resolverRutasParaMpdf($graficas);

        // ── 3. Anomalías (reutiliza datos ya descargados) ─────────────────
        $anomalias = $this->obtenerAnomalias($dispositivos, $fromDate, $toDate, $horariosPrefetchados, $mediasHorariaHistorico);

        // ── 4. Costes (usa total_kwh ya calculado, sin llamada extra a InfluxDB) ──
        $costeEstimado = $this->obtenerCosteEstimado($resumenPorDispositivo);

        // ── 5. LLM ────────────────────────────────────────────────────────
        $diasPeriodo     = max(1, (int) round(Carbon::parse($fechaInicio)->floatDiffInDays(Carbon::parse($fechaFin))) + 1);
        $totalGlobalCost = array_sum(array_column($costeEstimado, 'coste_estimado'));
        $totalAnomalias  = array_sum(array_map('count', $anomalias));

        $resumenGlobal = [
            'total_kwh'        => round($totalesGlobales, 3),
            'total_coste'      => round($totalGlobalCost, 2),
            'total_anomalias'  => $totalAnomalias,
            'dias_periodo'     => $diasPeriodo,
            'num_dispositivos' => count($resumenPorDispositivo),
        ];

        $datosParaLLM = $this->estructurarDatosParaLLM($datos, $resumenPorDispositivo, $metricasAvanzadas, $anomalias);
        $contexto     = compact('fromDate', 'toDate', 'resumenGlobal', 'metricasAvanzadas');

        $resumen                    = 'No se pudo generar el resumen automático.';
        $conclusion                 = 'No se pudo generar la conclusión automática.';
        $distribucionHorariaTextual = 'No se pudo generar el análisis de distribución horaria.';

        try {
            $prompts = [
                'resumen'             => $this->modeloLenguaje->buildPrompt('resumen',             $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo, $contexto),
                'conclusion'          => $this->modeloLenguaje->buildPrompt('conclusion',          $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo, $contexto),
                'distribucionHoraria' => $this->modeloLenguaje->buildPrompt('distribucionHoraria', $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo, $contexto),
            ];
            $llmResultados              = $this->modeloLenguaje->generarTextos($prompts);
            $resumen                    = $llmResultados['resumen']             ?: $resumen;
            $conclusion                 = $llmResultados['conclusion']          ?: $conclusion;
            $distribucionHorariaTextual = $llmResultados['distribucionHoraria'] ?: $distribucionHorariaTextual;
            Log::info('[InformeService] LLM completado');
        } catch (\Throwable $e) {
            Log::error('[InformeService] LLM falló, informe sin textos automáticos', ['error' => $e->getMessage()]);
        }

        // ── 6. PDF ────────────────────────────────────────────────────────
        $logoPath = public_path('assets/img/TERSIME.png');
        $logo     = file_exists($logoPath) ? 'file://' . $logoPath : null;

        $viewData = [
            'fromDate'                   => $fromDate,
            'toDate'                     => $toDate,
            'dispositivos'               => $dispositivos,
            'user'                       => $user,
            'logo'                       => $logo,
            'resumen'                    => $resumen,
            'resumenPorDispositivo'      => $resumenPorDispositivo,
            'metricasAvanzadas'          => $metricasAvanzadas,
            'resumenGlobal'              => $resumenGlobal,
            'tablaAnomalias'             => $anomalias,
            'costeEstimado'              => $costeEstimado,
            'graficas'                   => $graficas,
            'conclusion'                 => $conclusion,
            'distribucionHorariaTextual' => $distribucionHorariaTextual,
        ];

        $filename    = 'informe_' . $user->id . '_' . now()->format('Ymd_His') . '.pdf';
        $storagePath = 'public/informes/' . $filename;

        $html = view('informes.informe_bajo_demanda', $viewData)->render();

        $tempDir = storage_path('app/tmp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 14,
            'margin_bottom' => 14,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'tempDir'       => $tempDir,
            // Allow mPDF to load images from these filesystem roots
            'basepath'      => storage_path('app/'),
        ]);
        $mpdf->SetFooter('© ' . date('Y') . ' TERSIME — Informe generado automáticamente||Pág. {PAGENO}/{nbpg}');
        $mpdf->WriteHTML($html);
        Storage::put($storagePath, $mpdf->Output('', 'S'));
        $this->limpiarGraficasTemporales($archivosGraficas);

        $fileSize = 0;
        try {
            $fileSize = Storage::size($storagePath);
        } catch (\Throwable $e) {
            Log::warning('[InformeService] No se pudo obtener tamaño del PDF', ['error' => $e->getMessage()]);
        }

        $absolutePath = storage_path('app/' . $storagePath);

        return compact('filename', 'storagePath', 'absolutePath', 'fileSize');
    }

    // ── Helpers privados ───────────────────────────────────────────────────────

    private function limpiarGraficasTemporales(array $archivosGraficas): void
    {
        foreach ($archivosGraficas as $path) {
            if ($path && is_file($path)) {
                unlink($path);
            }
        }
    }

    private function buildDispositivosQuery($dispositivos): string
    {
        $query = '';
        foreach ($dispositivos as $d) {
            $tag    = is_object($d) ? ($d->influx_tag ?? $d->nombre ?? "device_{$d->id}") : (string) $d;
            $query .= '&var-dispositivos=' . urlencode($tag);
        }
        return $query;
    }

    private function epochToIso8601($epoch, string $timezone = 'UTC'): string
    {
        if ($epoch > 9999999999) {
            $epoch = $epoch / 1000;
        }
        return Carbon::createFromTimestamp($epoch, $timezone)->toIso8601ZuluString();
    }

    /**
     * @param array $horariosPrefetchados   ['tag' => ['iso_datetime' => kWh, ...]]
     * @param array $mediasHorariaHistorico ['tag' => ['H' => media, ...]]
     */
    private function obtenerAnomalias(
        \Illuminate\Support\Collection $dispositivos,
        string $fromDate,
        string $toDate,
        array $horariosPrefetchados = [],
        array $mediasHorariaHistorico = []
    ): array {
        $multiplicador = (float) config('tersime.anomalias.multiplicador', 3.5);
        if ($multiplicador <= 0) {
            Log::warning('[InformeService] multiplicador de anomalías inválido (' . $multiplicador . '), usando 3.5');
            $multiplicador = 3.5;
        }
        $anomalias = [];

        foreach ($dispositivos as $dispositivo) {
            $tag = is_object($dispositivo)
                ? ($dispositivo->influx_tag ?? $dispositivo->nombre ?? "device_{$dispositivo->id}")
                : (string) $dispositivo;

            $horarios = $horariosPrefetchados[$tag] ?? $this->influx->datosHorarios($tag, $fromDate, $toDate);

            if (empty($horarios)) {
                $anomalias[$tag] = [];
                continue;
            }

            $fromHistorico = Carbon::parse($toDate)->subYears(2)->toDateString();
            $mediaPorHora  = $mediasHorariaHistorico[$tag] ?? $this->influx->mediaPorHora($tag, $fromHistorico, $toDate);

            if (empty($mediaPorHora)) {
                $anomalias[$tag] = [];
                continue;
            }

            $lista = [];

            foreach ($horarios as $fechaIso => $valorKwh) {
                $hora = Carbon::parse($fechaIso)->format('H');

                if (!isset($mediaPorHora[$hora])) {
                    continue;
                }

                $mediaHora = (float) $mediaPorHora[$hora];
                $valor     = (float) $valorKwh;

                // Sin baseline histórico no se puede determinar si algo es anómalo
                if ($mediaHora <= 0) {
                    continue;
                }

                $umbralAlto = $mediaHora * $multiplicador;
                $umbralBajo = $mediaHora / $multiplicador;

                if ($valor > $umbralAlto) {
                    $lista[] = [
                        'tipo'                     => 'exceso',
                        'device'                   => $tag,
                        'fecha'                    => $fechaIso,
                        'hora'                     => $hora,
                        'valor_kwh'                => round($valor, 6),
                        'media_historica_hora_kwh' => round($mediaHora, 6),
                        'diferencia_kwh'           => round($valor - $mediaHora, 6),
                        'multiplicador_usado'      => $multiplicador,
                        'mensaje'                  => 'Exceso anormal de consumo horario',
                    ];
                } elseif ($valor < $umbralBajo) {
                    $lista[] = [
                        'tipo'                     => 'defecto',
                        'device'                   => $tag,
                        'fecha'                    => $fechaIso,
                        'hora'                     => $hora,
                        'valor_kwh'                => round($valor, 6),
                        'media_historica_hora_kwh' => round($mediaHora, 6),
                        'diferencia_kwh'           => round($mediaHora - $valor, 6),
                        'multiplicador_usado'      => $multiplicador,
                        'mensaje'                  => 'Defecto anormal de consumo horario',
                    ];
                }
            }

            $anomalias[$tag] = $lista;
        }

        return $anomalias;
    }

    /**
     * Calcula costes usando los totales ya computados en el paso 1, sin llamada extra a InfluxDB.
     */
    private function obtenerCosteEstimado(array $resumenPorDispositivo): array
    {
        $costePorKwh = (float) config('tersime.costes.kwh', 0.15);
        $resultados  = [];

        foreach ($resumenPorDispositivo as $row) {
            if (!empty($row['error'])) {
                continue;
            }
            $tag          = $row['device_key'];
            $consumoTotal = (float) ($row['total_kwh'] ?? 0.0);
            $resultados[$tag] = [
                'consumo_total_kwh' => round($consumoTotal, 6),
                'coste_estimado'    => round($consumoTotal * $costePorKwh, 6),
            ];
        }

        return $resultados;
    }

    private function renderizarGraficas(array $panelUrls, string $grafanaUser = 'admin'): array
    {
        $width  = (int) config('tersime.grafana.renderer_width', 1000);
        $height = (int) config('tersime.grafana.renderer_height', 500);

        $result = [];
        foreach ($panelUrls as $key => [$panelUrl, $nombreArchivo, $rendererTimeout]) {
            $phpTimeout = $rendererTimeout + 45;

            // Call Grafana's own render endpoint instead of the renderer directly.
            // Grafana authenticates via auth proxy header, then calls the renderer
            // internally with a renderKey so Chrome can authenticate.
            $renderUrl = preg_replace('#/d-solo/#', '/render/d-solo/', $panelUrl, 1);
            $renderUrl .= (str_contains($renderUrl, '?') ? '&' : '?')
                . "width={$width}&height={$height}";

            Log::info('[Renderer] Solicitando panel', [
                'key'              => $key,
                'renderer_timeout' => $rendererTimeout,
            ]);

            $result[$key] = null;
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $response = Http::withHeaders([
                        'X-WEBAUTH-USER' => $grafanaUser,
                        'Accept'         => 'image/png',
                    ])
                    ->timeout($phpTimeout)
                    ->get($renderUrl);

                    if ($response->successful()) {
                        Log::info('[Renderer] Panel OK', [
                            'key'     => $key,
                            'bytes'   => strlen($response->body()),
                            'attempt' => $attempt,
                        ]);
                        $path = "public/graficas/{$nombreArchivo}.png";
                        Storage::put($path, $response->body());
                        $result[$key] = storage_path("app/{$path}");
                        break;
                    }

                    Log::warning('[Renderer] HTTP error', [
                        'key'     => $key,
                        'status'  => $response->status(),
                        'attempt' => $attempt,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[Renderer] Excepción', [
                        'key'     => $key,
                        'error'   => $e->getMessage(),
                        'attempt' => $attempt,
                    ]);
                }

                if ($attempt < 2) {
                    sleep(3);
                }
            }

            sleep(1);
        }

        return $result;
    }

    /**
     * Calcula métricas avanzadas a partir de los datos horarios/diarios ya descargados:
     * franjas tarifarias (2.0TD España), tendencia lineal, picos y patrón semanal.
     */
    private function computarMetricasAvanzadas(array $resumenPorDispositivo): array
    {
        $tz       = config('app.timezone', 'Europe/Madrid');
        $metricas = [];

        foreach ($resumenPorDispositivo as $row) {
            $tag = $row['device_key'] ?? null;
            if (!$tag || !empty($row['error'])) {
                if ($tag) $metricas[$tag] = ['error' => true];
                continue;
            }

            $horas = $row['horas'] ?? [];
            $dias  = $row['dias']  ?? [];

            $franjas    = ['punta' => 0.0, 'llano' => 0.0, 'valle' => 0.0];
            $labTotal   = 0.0; $labCount   = 0;
            $findTotal  = 0.0; $findCount  = 0;
            $picosList  = [];
            // Día de semana: sumas acumuladas y conteos por día ISO (1=Lun … 7=Dom)
            $dowSums   = array_fill_keys(range(1, 7), 0.0);
            $dowCounts = array_fill_keys(range(1, 7), 0);

            foreach ($horas as $ts => $kwh) {
                try {
                    $dt   = Carbon::parse($ts)->setTimezone($tz);
                    $h    = (int) $dt->format('G');
                    $dow  = (int) $dt->format('N'); // 1=Lun, 7=Dom
                    $kwh  = (float) $kwh;

                    $dowSums[$dow]   += $kwh;
                    $dowCounts[$dow]++;

                    if ($dt->isWeekend()) {
                        $franjas['valle'] += $kwh;
                        $findTotal += $kwh; $findCount++;
                    } else {
                        $labTotal += $kwh; $labCount++;
                        if (($h >= 10 && $h < 14) || ($h >= 18 && $h < 22)) {
                            $franjas['punta'] += $kwh;
                        } elseif (($h >= 8 && $h < 10) || ($h >= 14 && $h < 18) || $h >= 22) {
                            $franjas['llano'] += $kwh;
                        } else {
                            $franjas['valle'] += $kwh;
                        }
                    }
                    $picosList[$ts] = $kwh;
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en métricas', ['ts' => $ts, 'error' => $e->getMessage()]);
                    continue;
                }
            }

            $dowLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
            $patronDiaSemana = [];
            foreach ($dowSums as $dow => $sum) {
                $c = $dowCounts[$dow];
                $patronDiaSemana[$dowLabels[$dow]] = $c > 0 ? round($sum / $c, 4) : null;
            }

            arsort($picosList);
            $topPicos = [];
            foreach (array_slice($picosList, 0, 5, true) as $ts => $kwh) {
                try {
                    $topPicos[] = [
                        'fecha' => Carbon::parse($ts)->setTimezone($tz)->format('d/m/Y H:i'),
                        'kwh'   => round($kwh, 4),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en top picos', ['ts' => $ts, 'error' => $e->getMessage()]);
                }
            }

            $diasValores = [];
            foreach ($dias as $ts => $kwh) {
                try {
                    $diasValores[Carbon::parse($ts)->setTimezone($tz)->format('d/m/Y')] = (float) $kwh;
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en top días', ['ts' => $ts, 'error' => $e->getMessage()]);
                }
            }
            arsort($diasValores);
            $topDias = array_slice($diasValores, 0, 5, true);

            // Regresión lineal simple para tendencia diaria (kWh/día)
            $tendencia = null;
            $diasArr   = array_values($dias);
            $n         = count($diasArr);
            if ($n >= 3) {
                $sumX = 0; $sumY = 0.0; $sumXY = 0.0; $sumX2 = 0;
                foreach ($diasArr as $i => $v) {
                    $sumX  += $i; $sumY  += (float) $v;
                    $sumXY += $i * (float) $v; $sumX2 += $i * $i;
                }
                $denom     = $n * $sumX2 - $sumX * $sumX;
                $tendencia = round(($n * $sumXY - $sumX * $sumY) / $denom, 5);
            }

            $totalFranjas = array_sum($franjas);
            $metricas[$tag] = [
                'franjas_tarifarias' => [
                    'punta_kwh'  => round($franjas['punta'], 3),
                    'llano_kwh'  => round($franjas['llano'], 3),
                    'valle_kwh'  => round($franjas['valle'], 3),
                    'punta_pct'  => $totalFranjas > 0 ? round($franjas['punta'] / $totalFranjas * 100, 1) : 0.0,
                    'llano_pct'  => $totalFranjas > 0 ? round($franjas['llano'] / $totalFranjas * 100, 1) : 0.0,
                    'valle_pct'  => $totalFranjas > 0 ? round($franjas['valle'] / $totalFranjas * 100, 1) : 0.0,
                ],
                'patron_semana' => [
                    'media_hora_laborable' => $labCount  > 0 ? round($labTotal  / $labCount,  4) : null,
                    'media_hora_festivo'   => $findCount > 0 ? round($findTotal / $findCount, 4) : null,
                ],
                'top_picos_horarios'  => $topPicos,
                'top_dias_consumo'    => $topDias,
                'tendencia_kwh_dia'   => $tendencia,
                'patron_dia_semana'   => $patronDiaSemana,
            ];
        }

        return $metricas;
    }

    /**
     * Construye el payload estructurado para el LLM: datos curados sin series brutas.
     */
    private function estructurarDatosParaLLM(
        array $datos,
        array $resumenPorDispositivo,
        array $metricasAvanzadas,
        array $anomalias
    ): array {
        $resumenByTag = [];
        foreach ($resumenPorDispositivo as $row) {
            $resumenByTag[$row['device_key'] ?? ''] = $row;
        }

        $result = [];
        foreach ($datos as $tag => $info) {
            $r = $resumenByTag[$tag] ?? [];
            $m = $metricasAvanzadas[$tag] ?? [];

            $nAnom = count($anomalias[$tag] ?? []);

            $result[$tag] = [
                'nombre'                  => $info['nombre-dispositivo'] ?? $tag,
                'total_kwh'               => $r['total_kwh'] ?? null,
                'media_kwh_hora'          => $r['mean_kwh_h'] ?? null,
                'max_kwh_hora'            => $r['max'] ?? null,
                'min_kwh_hora'            => $r['min'] ?? null,
                'stddev'                  => $r['stddev'] ?? null,
                'factor_carga'            => $r['factor_carga'] ?? null,
                'variacion_historico_pct' => $r['variation_percent'] ?? null,
                'pct_sobre_total'         => $r['pct_over_total'] ?? null,
                'media_horaria_periodo'   => $info['media-horaria'] ?? [],
                'media_horaria_historica' => $info['media-horaria-historico'] ?? [],
                'franjas_tarifarias'      => $m['franjas_tarifarias'] ?? null,
                'patron_semana'           => $m['patron_semana'] ?? null,
                'top_picos_horarios'      => $m['top_picos_horarios'] ?? [],
                'top_dias_consumo'        => $m['top_dias_consumo'] ?? [],
                'tendencia_kwh_dia'       => $m['tendencia_kwh_dia'] ?? null,
                'patron_dia_semana'       => $m['patron_dia_semana'] ?? null,
                'num_anomalias'           => $nAnom,
            ];
        }

        return $result;
    }

    private function resolveTag($dispositivo): string
    {
        return $dispositivo->influx_tag ?? $dispositivo->nombre ?? "device_{$dispositivo->id}";
    }

    private function resolverRutasParaMpdf(array $graficas): array
    {
        foreach ($graficas as $key => &$valor) {
            if (is_array($valor)) {
                $valor = $this->resolverRutasParaMpdf($valor);
            } elseif (is_string($valor) && $valor !== '' && file_exists($valor)) {
                $valor = 'file://' . $valor;
            }
        }
        unset($valor);
        return $graficas;
    }
}
