<?php

namespace App\Services;

use App\Services\InfluxService;
use App\Services\OpenRouterService;
use App\Models\Informe;
use App\Models\Ajuste;
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
        string $fechaDesde,
        string $fechaHasta,
        bool $telegram = false,
        bool $correo = false,
        bool $discord = false,
        ?string $correoDestino = null
    ): array {
        $data = $this->compilarGeneracion($user, $dispositivos, $fechaDesde, $fechaHasta, $telegram, $correo, $discord);

        $informe->update([
            'nombre_archivo' => $data['nombreArchivo'],
            'pdf_path'       => $data['rutaAlmacenamiento'],
            'tamano_bytes'   => $data['tamanoBytes'],
            'generado_en'    => now(),
        ]);

        $urlDescarga = route('informes.download', $informe->id, false);

        Log::info('[InformeService] PDF generado', ['nombre_archivo' => $data['nombreArchivo'], 'informe_id' => $informe->id]);

        return array_merge($data, compact('informe', 'urlDescarga'));
    }


    private function compilarGeneracion(
        User $user,
        \Illuminate\Support\Collection $dispositivos,
        string $fechaDesde,
        string $fechaHasta,
        bool $telegram,
        bool $correo,
        bool $discord
    ): array {
        $tieneTiempo  = fn(string $d) => strlen($d) > 10;
        $inicioMillis = ($tieneTiempo($fechaDesde) ? Carbon::parse($fechaDesde) : Carbon::parse($fechaDesde)->startOfDay())->timestamp * 1000;
        $finMillis    = ($tieneTiempo($fechaHasta)  ? Carbon::parse($fechaHasta)  : Carbon::parse($fechaHasta)->endOfDay())->timestamp  * 1000;
        $fechaInicio  = $this->epochAIso8601($inicioMillis);
        $fechaFin     = $this->epochAIso8601($finMillis);
        $inicio       = Carbon::parse($fechaInicio)->toDateString();
        $fin          = Carbon::parse($fechaFin)->toDateString();

        // ── 1. Datos por dispositivo (InfluxDB) ────────────────────────────
        $resumenPorDispositivo  = [];
        $totalesGlobales        = 0.0;
        $horariosPrefetchados   = [];
        $mediasHorariaHistorico = [];
        $horariosCache          = [];

        foreach ($dispositivos as $dispositivo) {
            try {
                $etiqueta = $this->resolverEtiqueta($dispositivo);

                $datosInflux = ['total' => 0.0, 'horas' => [], 'dias' => []];
                try {
                    $obtenido = $this->influx->resumen($etiqueta, $fechaInicio, $fechaFin);
                    if (is_array($obtenido)) {
                        $datosInflux = $obtenido;
                    }
                } catch (\Throwable $e) {
                    Log::error('[InformeService] Influx->resumen falló', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);
                }

                $total = isset($datosInflux['total']) ? (float) $datosInflux['total'] : 0.0;

                $estadisticas = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];
                $horariosCache[$etiqueta] = $datosInflux['horas'] ?? [];

                try {
                    $remoteStats = $this->influx->datosEstadisticos($etiqueta, $fechaInicio, $fechaFin);
                    if (is_array($remoteStats)) {
                        $estadisticas = array_merge($estadisticas, $remoteStats);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] datosEstadisticos falló', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);
                }

                $factorCarga    = null;
                $totalHistorico = null;

                try {
                    $factorCarga = $this->influx->factorCarga($etiqueta, $fechaInicio, $fechaFin);
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] factorCarga falló', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);
                }

                try {
                    $totalHistorico = $this->influx->mediaHistoricaPeriodo($etiqueta, $inicio, $fin);
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] mediaHistoricaPeriodo falló', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);
                }

                $porcentajeVariacion = null;
                if (!is_null($totalHistorico) && $totalHistorico != 0.0) {
                    $porcentajeVariacion = (($total - $totalHistorico) / $totalHistorico) * 100.0;
                }

                $resumenPorDispositivo[] = [
                    'id'                  => $dispositivo->id,
                    'nombre'              => $dispositivo->nombre ?? $etiqueta,
                    'etiqueta'            => $etiqueta,
                    'total_kwh'           => round($total, 4),
                    'media_kwh_h'         => isset($estadisticas['mean']) && is_numeric($estadisticas['mean']) ? round($estadisticas['mean'], 4) : null,
                    'stddev'              => isset($estadisticas['stddev']) && is_numeric($estadisticas['stddev']) ? round($estadisticas['stddev'], 4) : null,
                    'max'                 => isset($estadisticas['max']) && is_numeric($estadisticas['max']) ? round($estadisticas['max'], 4) : null,
                    'min'                 => isset($estadisticas['min']) && is_numeric($estadisticas['min']) ? round($estadisticas['min'], 4) : null,
                    'factor_carga'        => is_null($factorCarga) ? null : round($factorCarga, 6),
                    'total_historico'     => is_null($totalHistorico) ? null : round($totalHistorico, 4),
                    'variacion_porcentaje' => is_null($porcentajeVariacion) ? null : round($porcentajeVariacion, 2),
                    'horas'               => $datosInflux['horas'] ?? [],
                    'dias'                => $datosInflux['dias']  ?? [],
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
                    'etiqueta'      => $this->resolverEtiqueta($dispositivo),
                    'error'         => true,
                    'error_message' => $e->getMessage(),
                ];
            }
        }

        foreach ($resumenPorDispositivo as &$fila) {
            $fila['pct_sobre_total'] = (isset($fila['total_kwh']) && $totalesGlobales > 0)
                ? round(($fila['total_kwh'] / $totalesGlobales) * 100.0, 2)
                : 0.0;
        }
        unset($fila);

        $metricasAvanzadas = $this->computarMetricasAvanzadas($resumenPorDispositivo);

        foreach ($resumenPorDispositivo as &$fila) {
            unset($fila['horas'], $fila['dias']);
        }
        unset($fila);

        $baseGrafana       = rtrim(config('tersime.grafana.renderer_base_url') ?: Ajuste::get('grafana_base_url') ?: 'http://grafana:3000', '/');
        $consultaDispositivos = $this->construirQueryDispositivos($dispositivos);

        $urlPanelTendencia =
            "{$baseGrafana}/d-solo/eegznxsjl47i8b/dashboard-initiot"
            . "?orgId=1&from={$inicioMillis}&to={$finMillis}&timezone=Europe%2FMadrid{$consultaDispositivos}&theme=light&panelId=panel-1";

        $graficas          = [];
        $datos             = [];
        $timeoutDefecto    = (int) config('tersime.grafana.renderer_timeout', 90);
        $urlsPaneles = [
            'tiempo-real'    => [$urlPanelTendencia, 'tiempo-real', 180],
            'consumo-diario' => [
                "{$baseGrafana}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&from={$inicioMillis}&to={$finMillis}&timezone=Europe%2FMadrid{$consultaDispositivos}&theme=light&panelId=panel-7",
                'consumo-diario',
                120,
            ],
        ];

        $panel5DiaRef = Carbon::create(2025, 1, 1, 0, 0, 0, 'Europe/Madrid');
        $panel5Inicio = $panel5DiaRef->getTimestamp() * 1000;
        $panel5Fin    = $panel5DiaRef->copy()->endOfDay()->getTimestamp() * 1000;

        foreach ($dispositivos as $dispositivo) {
            $etiqueta        = $this->resolverEtiqueta($dispositivo);
            $inicioHistorico = Carbon::parse($fin)->subYears(2)->toDateString();

            $urlsPaneles["media-horaria-{$etiqueta}"] = [
                "{$baseGrafana}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start={$fechaInicio}&var-end={$fechaFin}&var-dispositivos={$etiqueta}&theme=light&panelId=panel-5&from={$panel5Inicio}&to={$panel5Fin}&timezone=Europe%2FMadrid",
                "media-horaria-{$etiqueta}",
                240,
            ];
            $inicioHistoricoIso = Carbon::parse($inicioHistorico)->toDateString() . 'T00:00:00Z';
            $urlsPaneles["media-horaria-historico-{$etiqueta}"] = [
                "{$baseGrafana}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start={$inicioHistoricoIso}&var-end={$fechaFin}&var-dispositivos={$etiqueta}&theme=light&panelId=panel-5&from={$panel5Inicio}&to={$panel5Fin}&timezone=Europe%2FMadrid",
                "media-horaria-historico-{$etiqueta}",
                300,
            ];

            $mediaHistorica = $this->influx->mediaPorHora($etiqueta, $inicioHistorico, $fin);

            $datos[$etiqueta] = [
                'media-horaria'           => $this->influx->mediaPorHora($etiqueta, $inicio, $fin),
                'media-horaria-historico' => $mediaHistorica,
                'nombre-dispositivo'      => $dispositivo->nombre,
            ];

            $horariosPrefetchados[$etiqueta]   = $horariosCache[$etiqueta] ?? [];
            $mediasHorariaHistorico[$etiqueta] = $mediaHistorica;
        }

        $archivosGraficas = $this->renderizarGraficas($urlsPaneles, $user->name ?? 'admin');

        foreach ($dispositivos as $dispositivo) {
            $etiqueta = $this->resolverEtiqueta($dispositivo);
            $graficas[$etiqueta] = [
                'media-horaria'           => $archivosGraficas["media-horaria-{$etiqueta}"] ?? null,
                'media-horaria-historico' => $archivosGraficas["media-horaria-historico-{$etiqueta}"] ?? null,
            ];
        }
        $graficas['tiempo-real']    = $archivosGraficas['tiempo-real']    ?? null;
        $graficas['consumo-diario'] = $archivosGraficas['consumo-diario'] ?? null;

        $graficas = $this->resolverRutasParaMpdf($graficas);

        $anomalias = $this->obtenerAnomalias($dispositivos, $fechaDesde, $fechaHasta, $horariosPrefetchados, $mediasHorariaHistorico);

        $costeEstimado = $this->obtenerCosteEstimado($resumenPorDispositivo);

        $diasPeriodo  = max(1, (int) round(Carbon::parse($fechaInicio)->floatDiffInDays(Carbon::parse($fechaFin))) + 1);
        $costeTotal   = array_sum(array_column($costeEstimado, 'coste_estimado'));
        $totalAnomalias = array_sum(array_map('count', $anomalias));

        $resumenGlobal = [
            'total_kwh'        => round($totalesGlobales, 3),
            'total_coste'      => round($costeTotal, 2),
            'total_anomalias'  => $totalAnomalias,
            'dias_periodo'     => $diasPeriodo,
            'num_dispositivos' => count($resumenPorDispositivo),
        ];

        $datosParaLLM = $this->estructurarDatosParaLLM($datos, $resumenPorDispositivo, $metricasAvanzadas, $anomalias);
        $contexto     = [
            'fromDate'          => $fechaDesde,
            'toDate'            => $fechaHasta,
            'resumenGlobal'     => $resumenGlobal,
            'metricasAvanzadas' => $metricasAvanzadas,
        ];

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

        $logoPath = public_path('assets/img/TERSIME.png');
        $logo     = file_exists($logoPath) ? 'file://' . $logoPath : null;

        $datosVista = [
            'fechaDesde'                 => $fechaDesde,
            'fechaHasta'                 => $fechaHasta,
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

        $nombreArchivo       = 'informe_' . $user->id . '_' . now()->format('Ymd_His') . '.pdf';
        $rutaAlmacenamiento  = 'public/informes/' . $nombreArchivo;

        $html = view('informes.informe_bajo_demanda', $datosVista)->render();

        $dirTemporal = storage_path('app/tmp');
        if (!is_dir($dirTemporal)) {
            mkdir($dirTemporal, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 14,
            'margin_bottom' => 14,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'tempDir'       => $dirTemporal,
            'basepath'      => storage_path('app/'),
        ]);
        $mpdf->SetFooter('© ' . date('Y') . ' TERSIME — Informe generado automáticamente||Pág. {PAGENO}/{nbpg}');
        $prev = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', PHP_INT_MAX);
        $mpdf->WriteHTML($html);
        ini_set('pcre.backtrack_limit', $prev);
        Storage::put($rutaAlmacenamiento, $mpdf->Output('', 'S'));
        $this->limpiarGraficasTemporales($archivosGraficas);

        $tamanoBytes = 0;
        try {
            $tamanoBytes = Storage::size($rutaAlmacenamiento);
        } catch (\Throwable $e) {
            Log::warning('[InformeService] No se pudo obtener tamaño del PDF', ['error' => $e->getMessage()]);
        }

        $rutaAbsoluta = storage_path('app/' . $rutaAlmacenamiento);

        return compact('nombreArchivo', 'rutaAlmacenamiento', 'rutaAbsoluta', 'tamanoBytes');
    }


    private function limpiarGraficasTemporales(array $archivosGraficas): void
    {
        foreach ($archivosGraficas as $ruta) {
            if ($ruta && is_file($ruta)) {
                unlink($ruta);
            }
        }
    }

    private function construirQueryDispositivos($dispositivos): string
    {
        $consulta = '';
        foreach ($dispositivos as $d) {
            $etiqueta  = is_object($d) ? ($d->etiqueta_influx ?? $d->nombre ?? "device_{$d->id}") : (string) $d;
            $consulta .= '&var-dispositivos=' . urlencode($etiqueta);
        }
        return $consulta;
    }

    private function epochAIso8601($epoch, string $timezone = 'UTC'): string
    {
        if ($epoch > 9999999999) {
            $epoch = $epoch / 1000;
        }
        return Carbon::createFromTimestamp($epoch, $timezone)->toIso8601ZuluString();
    }

    /**
     * @param array $horariosPrefetchados   ['etiqueta' => ['iso_datetime' => kWh, ...]]
     * @param array $mediasHorariaHistorico ['etiqueta' => ['H' => media, ...]]
     */
    private function obtenerAnomalias(
        \Illuminate\Support\Collection $dispositivos,
        string $fechaDesde,
        string $fechaHasta,
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
            $etiqueta = is_object($dispositivo)
                ? ($dispositivo->etiqueta_influx ?? $dispositivo->nombre ?? "device_{$dispositivo->id}")
                : (string) $dispositivo;

            $horarios = $horariosPrefetchados[$etiqueta] ?? $this->influx->datosHorarios($etiqueta, $fechaDesde, $fechaHasta);

            if (empty($horarios)) {
                $anomalias[$etiqueta] = [];
                continue;
            }

            $inicioHistorico = Carbon::parse($fechaHasta)->subYears(2)->toDateString();
            $mediaPorHora    = $mediasHorariaHistorico[$etiqueta] ?? $this->influx->mediaPorHora($etiqueta, $inicioHistorico, $fechaHasta);

            if (empty($mediaPorHora)) {
                $anomalias[$etiqueta] = [];
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
                        'etiqueta'                 => $etiqueta,
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
                        'etiqueta'                 => $etiqueta,
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

            $anomalias[$etiqueta] = $lista;
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

        foreach ($resumenPorDispositivo as $fila) {
            if (!empty($fila['error'])) {
                continue;
            }
            $etiqueta         = $fila['etiqueta'];
            $consumoTotal     = (float) ($fila['total_kwh'] ?? 0.0);
            $resultados[$etiqueta] = [
                'consumo_total_kwh' => round($consumoTotal, 6),
                'coste_estimado'    => round($consumoTotal * $costePorKwh, 6),
            ];
        }

        return $resultados;
    }

    private function renderizarGraficas(array $urlsPaneles, string $grafanaUser = 'admin'): array
    {
        $ancho  = (int) config('tersime.grafana.renderer_width', 1000);
        $alto   = (int) config('tersime.grafana.renderer_height', 500);

        $resultado = [];
        foreach ($urlsPaneles as $clave => [$urlPanel, $nombreArchivo, $timeoutRenderer]) {
            $timeoutPhp = $timeoutRenderer + 120;

            $urlRender = preg_replace('#/d-solo/#', '/render/d-solo/', $urlPanel, 1);
            $urlRender .= (str_contains($urlRender, '?') ? '&' : '?')
                . "width={$ancho}&height={$alto}&timeout={$timeoutRenderer}";

            Log::info('[Renderer] Solicitando panel', [
                'clave'            => $clave,
                'renderer_timeout' => $timeoutRenderer,
            ]);

            $resultado[$clave] = null;
            for ($intento = 1; $intento <= 2; $intento++) {
                try {
                    $respuesta = Http::withHeaders([
                        'X-WEBAUTH-USER' => $grafanaUser,
                        'Accept'         => 'image/png',
                    ])
                    ->timeout($timeoutPhp)
                    ->get($urlRender);

                    if ($respuesta->successful()) {
                        Log::info('[Renderer] Panel OK', [
                            'clave'   => $clave,
                            'bytes'   => strlen($respuesta->body()),
                            'intento' => $intento,
                        ]);
                        $ruta = "public/graficas/{$nombreArchivo}.png";
                        Storage::put($ruta, $respuesta->body());
                        $resultado[$clave] = storage_path("app/{$ruta}");
                        break;
                    }

                    Log::warning('[Renderer] HTTP error', [
                        'clave'   => $clave,
                        'status'  => $respuesta->status(),
                        'intento' => $intento,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[Renderer] Excepción', [
                        'clave'   => $clave,
                        'error'   => $e->getMessage(),
                        'intento' => $intento,
                    ]);
                }

                if ($intento < 2) {
                    sleep(30);
                }
            }

            sleep(15);
        }

        return $resultado;
    }

    private function computarMetricasAvanzadas(array $resumenPorDispositivo): array
    {
        $tz       = config('app.timezone', 'Europe/Madrid');
        $metricas = [];

        foreach ($resumenPorDispositivo as $fila) {
            $etiqueta = $fila['etiqueta'] ?? null;
            if (!$etiqueta || !empty($fila['error'])) {
                if ($etiqueta) $metricas[$etiqueta] = ['error' => true];
                continue;
            }

            $horas = $fila['horas'] ?? [];
            $dias  = $fila['dias']  ?? [];

            $franjas          = ['punta' => 0.0, 'llano' => 0.0, 'valle' => 0.0];
            $totalLaborable   = 0.0; $conteoLaborable = 0;
            $totalFestivo     = 0.0; $conteoFestivo   = 0;
            $listaPicos       = [];

            $sumasDiaSemana   = array_fill_keys(range(1, 7), 0.0);
            $conteosDiaSemana = array_fill_keys(range(1, 7), 0);

            foreach ($horas as $ts => $kwh) {
                try {
                    $dt        = Carbon::parse($ts)->setTimezone($tz);
                    $hora      = (int) $dt->format('G');
                    $diaSemana = (int) $dt->format('N'); // 1=Lun, 7=Dom
                    $kwh       = (float) $kwh;

                    $sumasDiaSemana[$diaSemana]   += $kwh;
                    $conteosDiaSemana[$diaSemana]++;

                    if ($dt->isWeekend()) {
                        $franjas['valle'] += $kwh;
                        $totalFestivo += $kwh; $conteoFestivo++;
                    } else {
                        $totalLaborable += $kwh; $conteoLaborable++;
                        if (($hora >= 10 && $hora < 14) || ($hora >= 18 && $hora < 22)) {
                            $franjas['punta'] += $kwh;
                        } elseif (($hora >= 8 && $hora < 10) || ($hora >= 14 && $hora < 18) || $hora >= 22) {
                            $franjas['llano'] += $kwh;
                        } else {
                            $franjas['valle'] += $kwh;
                        }
                    }
                    $listaPicos[$ts] = $kwh;
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en métricas', ['ts' => $ts, 'error' => $e->getMessage()]);
                    continue;
                }
            }

            $etiquetasDiaSemana = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
            $patronDiaSemana    = [];
            foreach ($sumasDiaSemana as $diaSemana => $suma) {
                $c = $conteosDiaSemana[$diaSemana];
                $patronDiaSemana[$etiquetasDiaSemana[$diaSemana]] = $c > 0 ? round($suma / $c, 4) : null;
            }

            arsort($listaPicos);
            $topPicos = [];
            foreach (array_slice($listaPicos, 0, 5, true) as $ts => $kwh) {
                try {
                    $topPicos[] = [
                        'fecha' => Carbon::parse($ts)->setTimezone($tz)->format('d/m/Y H:i'),
                        'kwh'   => round($kwh, 4),
                    ];
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en top picos', ['ts' => $ts, 'error' => $e->getMessage()]);
                }
            }

            $valoresDias = [];
            foreach ($dias as $ts => $kwh) {
                try {
                    $valoresDias[Carbon::parse($ts)->setTimezone($tz)->format('d/m/Y')] = (float) $kwh;
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] timestamp inválido en top días', ['ts' => $ts, 'error' => $e->getMessage()]);
                }
            }
            arsort($valoresDias);
            $topDias = array_slice($valoresDias, 0, 5, true);

            $tendencia = null;
            $diasLista = array_values($dias);
            $n         = count($diasLista);
            if ($n >= 3) {
                $sumX = 0; $sumY = 0.0; $sumXY = 0.0; $sumX2 = 0;
                foreach ($diasLista as $i => $v) {
                    $sumX  += $i; $sumY  += (float) $v;
                    $sumXY += $i * (float) $v; $sumX2 += $i * $i;
                }
                $denominador = $n * $sumX2 - $sumX * $sumX;
                $tendencia   = round(($n * $sumXY - $sumX * $sumY) / $denominador, 5);
            }

            $totalFranjas = array_sum($franjas);
            $metricas[$etiqueta] = [
                'franjas_tarifarias' => [
                    'punta_kwh'  => round($franjas['punta'], 3),
                    'llano_kwh'  => round($franjas['llano'], 3),
                    'valle_kwh'  => round($franjas['valle'], 3),
                    'punta_pct'  => $totalFranjas > 0 ? round($franjas['punta'] / $totalFranjas * 100, 1) : 0.0,
                    'llano_pct'  => $totalFranjas > 0 ? round($franjas['llano'] / $totalFranjas * 100, 1) : 0.0,
                    'valle_pct'  => $totalFranjas > 0 ? round($franjas['valle'] / $totalFranjas * 100, 1) : 0.0,
                ],
                'patron_semana' => [
                    'media_hora_laborable' => $conteoLaborable > 0 ? round($totalLaborable / $conteoLaborable, 4) : null,
                    'media_hora_festivo'   => $conteoFestivo   > 0 ? round($totalFestivo   / $conteoFestivo,   4) : null,
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
        $resumenPorEtiqueta = [];
        foreach ($resumenPorDispositivo as $fila) {
            $resumenPorEtiqueta[$fila['etiqueta'] ?? ''] = $fila;
        }

        $resultado = [];
        foreach ($datos as $etiqueta => $info) {
            $r = $resumenPorEtiqueta[$etiqueta] ?? [];
            $m = $metricasAvanzadas[$etiqueta]  ?? [];

            $numAnomalias = count($anomalias[$etiqueta] ?? []);

            $resultado[$etiqueta] = [
                'nombre'                  => $info['nombre-dispositivo'] ?? $etiqueta,
                'total_kwh'               => $r['total_kwh']            ?? null,
                'media_kwh_hora'          => $r['media_kwh_h']          ?? null,
                'max_kwh_hora'            => $r['max']                  ?? null,
                'min_kwh_hora'            => $r['min']                  ?? null,
                'stddev'                  => $r['stddev']               ?? null,
                'factor_carga'            => $r['factor_carga']         ?? null,
                'variacion_historico_pct' => $r['variacion_porcentaje'] ?? null,
                'pct_sobre_total'         => $r['pct_sobre_total']      ?? null,
                'media_horaria_periodo'   => $info['media-horaria']           ?? [],
                'media_horaria_historica' => $info['media-horaria-historico'] ?? [],
                'franjas_tarifarias'      => $m['franjas_tarifarias']   ?? null,
                'patron_semana'           => $m['patron_semana']        ?? null,
                'top_picos_horarios'      => $m['top_picos_horarios']   ?? [],
                'top_dias_consumo'        => $m['top_dias_consumo']     ?? [],
                'tendencia_kwh_dia'       => $m['tendencia_kwh_dia']    ?? null,
                'patron_dia_semana'       => $m['patron_dia_semana']    ?? null,
                'num_anomalias'           => $numAnomalias,
            ];
        }

        return $resultado;
    }

    private function resolverEtiqueta($dispositivo): string
    {
        return $dispositivo->etiqueta_influx ?? $dispositivo->nombre ?? "device_{$dispositivo->id}";
    }

    private function resolverRutasParaMpdf(array $graficas): array
    {
        foreach ($graficas as $clave => &$valor) {
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
