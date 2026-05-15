<?php

namespace App\Services;

use App\Http\Controllers\InfluxController;
use App\Http\Controllers\OpenRouterController;
use App\Models\Informe;
use App\Models\Setting;
use App\Models\User;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InformeService
{
    protected InfluxController $influx;
    protected OpenRouterController $modeloLenguaje;

    public function __construct(InfluxController $influx, OpenRouterController $modeloLenguaje)
    {
        $this->influx         = $influx;
        $this->modeloLenguaje = $modeloLenguaje;
    }

    /**
     * Genera un PDF, lo almacena y crea el registro en BD.
     *
     * @return array{informe: Informe, filename: string, absolutePath: string, downloadUrl: string}
     */
    public function generarPdf(
        User $user,
        $dispositivos,
        string $fromDate,
        string $toDate,
        string $tipo = 'Demanda',
        ?string $email = null,
        bool $telegram = false,
        bool $correo = false,
        bool $discord = false,
        ?string $correoDestino = null
    ): array {
        $dispositivosIds = $dispositivos->pluck('id')->toArray();

        // ── 1. Datos por dispositivo ────────────────────────────────────────
        $resumenPorDispositivo = [];
        $totalesGlobales       = 0.0;
        $horariosPrefetchados  = [];   // reutilizados en obtenerAnomalias()

        foreach ($dispositivos as $dispositivo) {
            try {
                $tag = $dispositivo->influx_tag ?? $dispositivo->nombre ?? "device_{$dispositivo->id}";

                $res = ['total' => 0.0, 'horas' => [], 'dias' => []];
                try {
                    $fetched = $this->influx->resumen($tag, $fromDate, $toDate);
                    if (is_array($fetched)) {
                        $res = $fetched;
                    }
                } catch (\Throwable $e) {
                    Log::error('[InformeService] Influx->resumen falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                $total = isset($res['total']) ? (float) $res['total'] : 0.0;

                $stats = ['mean' => null, 'stddev' => null, 'max' => null, 'min' => null, 'sum' => null];
                try {
                    $remoteStats = $this->influx->datosEstadisticos($tag, $fromDate, $toDate);
                    if (is_array($remoteStats)) {
                        $stats = array_merge($stats, $remoteStats);
                    }
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] datosEstadisticos falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                $factorCarga     = null;
                $historicalTotal = null;

                try {
                    $factorCarga = $this->influx->factorCarga($tag, $fromDate, $toDate);
                } catch (\Throwable $e) {
                    Log::warning('[InformeService] factorCarga falló', ['device' => $tag, 'error' => $e->getMessage()]);
                }

                try {
                    $historicalTotal = $this->influx->mediaHistoricaPeriodo($tag, $fromDate, $toDate);
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
                    'device_key'    => $dispositivo->influx_tag ?? null,
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

        // ── 2. Gráficas + datos horarios (una sola llamada por dispositivo) ─
        $grafanaBase  = rtrim(Setting::get('grafana_base_url') ?: config('app.grafana_renderer_base_url', 'http://localhost:3000'), '/');
        $fromMillis   = Carbon::parse($fromDate)->startOfDay()->timestamp * 1000;
        $toMillis     = Carbon::parse($toDate)->endOfDay()->timestamp * 1000;
        $fechaInicio  = $this->epochToIso8601($fromMillis);
        $fechaFin     = $this->epochToIso8601($toMillis);
        $dispositivosQuery = $this->buildDispositivosQuery($dispositivos);

        $panelUrlTendencia =
            "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
            . "?orgId=1&from={$fromMillis}&to={$toMillis}&timezone=browser{$dispositivosQuery}&theme=light&panelId=panel-1";

        $graficas      = [];
        $datos         = [];
        $panelUrls     = ['tiempo-real' => [$panelUrlTendencia, 'tiempo-real']];
        $start         = Carbon::parse($fechaInicio)->toDateString();
        $end           = Carbon::parse($fechaFin)->toDateString();

        foreach ($dispositivos as $dispositivo) {
            $tag = $dispositivo->influx_tag;

            // panel-5 hardcodes all data timestamps to 2025-01-01T{hour}:00:00+01:00 in its Flux query,
            // so from/to must cover that day in Europe/Madrid time for the chart to render correctly.
            $panel5From = 1735682400000; // 2025-01-01 00:00:00 Europe/Madrid = 2024-12-31 23:00:00 UTC
            $panel5To   = 1735768799000; // 2025-01-01 23:59:59 Europe/Madrid = 2025-01-01 22:59:59 UTC
            $panelUrls["media-horaria-{$tag}"] = [
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start={$fechaInicio}&var-end={$fechaFin}&var-dispositivos={$tag}&theme=light&panelId=panel-5&from={$panel5From}&to={$panel5To}&timezone=Europe%2FMadrid",
                "media-horaria-{$tag}",
            ];
            $panelUrls["media-horaria-historico-{$tag}"] = [
                "{$grafanaBase}/d-solo/eegznxsjl47i8b/dashboard-initiot"
                . "?orgId=1&var-start=2024-09-01T00:00:00Z&var-end={$fechaFin}&var-dispositivos={$tag}&theme=light&panelId=panel-5&from={$panel5From}&to={$panel5To}&timezone=Europe%2FMadrid",
                "media-horaria-historico-{$tag}",
            ];

            $horariosBrutos = $this->influx->datosHorarios($tag, $fechaInicio, $fechaFin);

            $datos[$tag] = [
                'media-horaria'           => $this->influx->mediaPorHora($tag, $start, $end),
                'media-horaria-historico' => $this->influx->mediaPorHora($tag, '2025-01-01', $end),
                'bruto-dispositivo'       => $horariosBrutos,
                'nombre-dispositivo'      => $dispositivo->nombre,
            ];

            $horariosPrefetchados[$tag] = $horariosBrutos;
        }

        // Descargar todas las gráficas en paralelo
        $archivosGraficas = $this->descargarGrafanasParalelo($panelUrls);

        foreach ($dispositivos as $dispositivo) {
            $tag = $dispositivo->influx_tag;
            $graficas[$tag] = [
                'media-horaria'           => $archivosGraficas["media-horaria-{$tag}"] ?? null,
                'media-horaria-historico' => $archivosGraficas["media-horaria-historico-{$tag}"] ?? null,
            ];
        }
        $graficas['tiempo-real'] = $archivosGraficas['tiempo-real'] ?? null;

        // Convertir rutas de imagen a data URIs base64 para DomPDF
        $graficas = $this->convertirGraficasABase64($graficas);

        // ── 3. Anomalías (reutiliza horarios ya descargados) ───────────────
        $anomalias = $this->obtenerAnomalias($dispositivos, $fromDate, $toDate, $horariosPrefetchados);

        // ── 4. Costes ──────────────────────────────────────────────────────
        $costeEstimado = $this->obtenerCosteEstimado($dispositivos, $fromDate, $toDate);

        // ── 5. LLM (paralelo) ─────────────────────────────────────────────
        Log::info('[InformeService] Iniciando LLM paralelo');

        // Sanitizar datos: quitar bruto-dispositivo para no sobrecargar el contexto del LLM
        $datosParaLLM = $this->sanitizarDatosParaLLM($datos);

        $resumen                    = 'No se pudo generar el resumen automático.';
        $conclusion                 = 'No se pudo generar la conclusión automática.';
        $distribucionHorariaTextual = 'No se pudo generar el análisis de distribución horaria.';

        try {
            $prompts = [
                'resumen'             => $this->modeloLenguaje->buildPrompt('resumen',             $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo),
                'conclusion'          => $this->modeloLenguaje->buildPrompt('conclusion',          $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo),
                'distribucionHoraria' => $this->modeloLenguaje->buildPrompt('distribucionHoraria', $datosParaLLM, $anomalias, $costeEstimado, $resumenPorDispositivo),
            ];

            $llmResultados = $this->modeloLenguaje->generarTextoParalelo($prompts);

            $resumen                    = $llmResultados['resumen']             ?: $resumen;
            $conclusion                 = $llmResultados['conclusion']          ?: $conclusion;
            $distribucionHorariaTextual = $llmResultados['distribucionHoraria'] ?: $distribucionHorariaTextual;

            Log::info('[InformeService] LLM paralelo completado');
        } catch (\Throwable $e) {
            Log::error('[InformeService] LLM falló, el informe se generará sin textos automáticos', ['error' => $e->getMessage()]);
        }

        // ── 6. PDF ────────────────────────────────────────────────────────
        $notificacionesLista = array_values(array_filter([
            $telegram ? 'telegram' : null,
            $correo   ? 'correo'   : null,
            $discord  ? 'discord'  : null,
        ]));

        $logoPath = public_path('assets/img/TERSIME.png');
        $logo     = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $viewData = [
            'fromDate'                   => $fromDate,
            'toDate'                     => $toDate,
            'dispositivos'               => $dispositivos,
            'email'                      => $email,
            'notificaciones'             => $notificacionesLista,
            'user'                       => $user,
            'logo'                       => $logo,
            'resumen'                    => $resumen,
            'resumenPorDispositivo'      => $resumenPorDispositivo,
            'tablaResumen'               => null,
            'tablaAnomalias'             => $anomalias,
            'costeEstimado'              => $costeEstimado,
            'conclusiones'               => null,
            'graficas'                   => $graficas,
            'conclusion'                 => $conclusion,
            'distribucionHorariaTextual' => $distribucionHorariaTextual,
        ];

        $filename    = 'informe_' . $user->id . '_' . now()->format('Ymd_His') . '.pdf';
        $storagePath = 'public/informes/' . $filename;

        $html = view('informes.informe_bajo_demanda', $viewData)->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4',
            'margin_top'    => 0,
            'margin_bottom' => 15,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'tempDir'       => storage_path('app/tmp'),
        ]);
        $mpdf->SetFooter('© ' . date('Y') . ' TERSIME — Informe generado automáticamente||Pág. {PAGENO}/{nbpg}');
        $mpdf->WriteHTML($html);

        Storage::put($storagePath, $mpdf->Output('', 'S'));

        $fileSize = 0;
        try {
            $fileSize = Storage::size($storagePath);
        } catch (\Throwable $e) {
            Log::warning('[InformeService] No se pudo obtener tamaño del PDF', ['error' => $e->getMessage()]);
        }

        $informe = Informe::create([
            'user_id'        => $user->id,
            'tipo'           => $tipo,
            'nombre_archivo' => $filename,
            'pdf_path'       => $storagePath,
            'periodo_from'   => $fromDate,
            'periodo_to'     => $toDate,
            'size_bytes'     => $fileSize,
            'generated_at'   => now(),
            'telegram'       => $telegram,
            'discord'        => $discord,
            'correo'         => $correo,
            'correo_destino' => $correoDestino,
            'activo'         => false,
        ]);

        if (!empty($dispositivosIds)) {
            $informe->dispositivos()->sync($dispositivosIds);
        }

        $absolutePath = storage_path('app/' . $storagePath);
        $downloadUrl  = route('informes.demanda.download', ['filename' => $filename]);

        Log::info('[InformeService] PDF generado', ['filename' => $filename, 'user_id' => $user->id]);

        return compact('informe', 'filename', 'absolutePath', 'downloadUrl', 'storagePath');
    }

    // ── Helpers privados ───────────────────────────────────────────────────

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

    private function descargarGrafanaRenderer(string $panelUrl, string $nombreArchivo): ?string
    {
        try {
            $rendererBase = rtrim(Setting::get('grafana_renderer_url') ?: config('tersime.grafana.renderer_url', 'http://localhost:8081/render'), '/');
            $width        = config('tersime.grafana.renderer_width', 1000);
            $height       = config('tersime.grafana.renderer_height', 500);
            $timeout      = config('tersime.grafana.renderer_timeout', 60);
            $token        = config('tersime.grafana.renderer_token');

            $rendererUrl = "{$rendererBase}?url=" . urlencode($panelUrl)
                . "&width={$width}&height={$height}&timeout={$timeout}";

            $response = Http::withHeaders([
                'X-Auth-Token' => $token,
                'Accept'       => 'image/png',
            ])
                ->withOptions(['verify' => false])
                ->timeout(90)
                ->get($rendererUrl);

            if ($response->successful()) {
                $path = "public/graficas/{$nombreArchivo}.png";
                Storage::put($path, $response->body());
                return storage_path("app/{$path}");
            }

            Log::error('[InformeService] Grafana renderer falló', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('[InformeService] Excepción en Grafana renderer', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * @param array $horariosPrefetchados  ['influx_tag' => ['iso_datetime' => kWh, ...]]
     */
    private function obtenerAnomalias($dispositivos, string $fromDate, string $toDate, array $horariosPrefetchados = []): array
    {
        $multiplicador = (float) config('tersime.anomalias.multiplicador', 3.5);
        $anomalias     = [];

        foreach ($dispositivos as $dispositivo) {
            $tag = is_object($dispositivo)
                ? ($dispositivo->influx_tag ?? $dispositivo->nombre ?? "device_{$dispositivo->id}")
                : (string) $dispositivo;

            // Reutilizar datos ya descargados para evitar llamada duplicada a InfluxDB
            $horarios = $horariosPrefetchados[$tag] ?? $this->influx->datosHorarios($tag, $fromDate, $toDate);

            if (empty($horarios)) {
                $anomalias[$tag] = [];
                continue;
            }

            $fromHistorico = Carbon::parse($toDate)->subYears(2)->toDateString();
            $mediaPorHora  = $this->influx->mediaPorHora($tag, $fromHistorico, $toDate);

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

                $mediaHora  = (float) $mediaPorHora[$hora];
                $valor      = (float) $valorKwh;
                $umbralAlto = $mediaHora * $multiplicador;
                $umbralBajo = $multiplicador == 0 ? $mediaHora : $mediaHora / $multiplicador;

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

    private function obtenerCosteEstimado($dispositivos, string $fromDate, string $toDate): array
    {
        $costePorKwh = (float) config('tersime.costes.kwh', 3.5);
        $resultados  = [];

        foreach ($dispositivos as $dispositivo) {
            $tag = is_object($dispositivo)
                ? ($dispositivo->influx_tag ?? $dispositivo->nombre ?? "device_{$dispositivo->id}")
                : (string) $dispositivo;

            $consumoTotal = $this->influx->consumoTotal($tag, $fromDate, $toDate);

            $resultados[$tag] = [
                'consumo_total_kwh' => round($consumoTotal, 6),
                'coste_estimado'    => round($consumoTotal * $costePorKwh, 6),
            ];
        }

        return $resultados;
    }

    /**
     * Descarga todas las gráficas de Grafana en paralelo con Http::pool().
     * $panelUrls = ['key' => ['url', 'nombreArchivo'], ...]
     * Retorna ['key' => '/ruta/absoluta.png' | null, ...]
     */
    private function descargarGrafanasParalelo(array $panelUrls): array
    {
        $rendererBase = rtrim(config('tersime.grafana.renderer_url', 'http://localhost:8081/render'), '/');
        $width        = config('tersime.grafana.renderer_width', 1000);
        $height       = config('tersime.grafana.renderer_height', 500);
        $timeout      = config('tersime.grafana.renderer_timeout', 60);
        $token        = config('tersime.grafana.renderer_token');

        $result = [];
        foreach ($panelUrls as $key => [$panelUrl, $nombreArchivo]) {
            try {
                // panel-1 (tiempo-real) es más complejo y necesita más tiempo
                $rendererTimeout = str_contains($panelUrl, 'panelId=panel-1') ? 120 : $timeout;
                $curlTimeout     = $rendererTimeout + 15;
                $url = "{$rendererBase}?url=" . urlencode($panelUrl) . "&width={$width}&height={$height}&timeout={$rendererTimeout}";
                $response = Http::withHeaders([
                    'X-Auth-Token' => $token,
                    'Accept'       => 'image/png',
                ])->withOptions(['verify' => false])->timeout($curlTimeout)->get($url);

                if ($response->successful()) {
                    $path = "public/graficas/{$nombreArchivo}.png";
                    Storage::put($path, $response->body());
                    $result[$key] = storage_path("app/{$path}");
                } else {
                    Log::error('[InformeService] Grafana renderer falló', ['key' => $key, 'status' => $response->status()]);
                    $result[$key] = null;
                }
            } catch (\Throwable $e) {
                Log::error('[InformeService] Grafana renderer excepción', ['key' => $key, 'error' => $e->getMessage()]);
                $result[$key] = null;
            }
        }

        return $result;
    }

    /**
     * Elimina datos crudos voluminosos antes de pasarlos al LLM
     * para no saturar el contexto ni confundir al modelo.
     */
    private function sanitizarDatosParaLLM(array $datos): array
    {
        $sanitizado = [];
        foreach ($datos as $tag => $info) {
            $sanitizado[$tag] = [
                'nombre-dispositivo'      => $info['nombre-dispositivo'] ?? $tag,
                'media-horaria'           => $info['media-horaria'] ?? [],
                'media-horaria-historico' => $info['media-horaria-historico'] ?? [],
            ];
        }
        return $sanitizado;
    }

    /**
     * Convierte todas las rutas de imagen en el array $graficas a data URIs base64,
     * para que DomPDF pueda incrustarlas sin acceso a disco externo.
     */
    private function convertirGraficasABase64(array $graficas): array
    {
        foreach ($graficas as $key => &$valor) {
            if (is_array($valor)) {
                $valor = $this->convertirGraficasABase64($valor);
            } elseif (is_string($valor) && $valor !== '' && file_exists($valor)) {
                $mime    = mime_content_type($valor) ?: 'image/png';
                $base64  = base64_encode(file_get_contents($valor));
                $valor   = "data:{$mime};base64,{$base64}";
            }
        }
        unset($valor);

        return $graficas;
    }
}
