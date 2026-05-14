<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Informe de Consumo Energético</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 0;
            background-color: #f9f9f9;
        }

        header {
            background-color: #0d47a1;
            color: white;
            text-align: center;
            padding: 20px 0;
        }

        header img {
            height: 55px;
            margin-bottom: 6px;
        }

        h1 {
            font-size: 22px;
            margin: 0;
            letter-spacing: 0.5px;
        }

        h2 {
            font-size: 16px;
            margin-top: 28px;
            color: #0d47a1;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            text-transform: uppercase;
        }

        h3 {
            font-size: 13px;
            color: #333;
            margin-top: 18px;
        }

        .meta {
            font-size: 11px;
            color: #f0f0f0;
            margin-top: 8px;
        }

        .content {
            padding: 25px 30px;
            background-color: white;
        }

        .section {
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: left;
            font-size: 11px;
        }

        th {
            background-color: #e3f2fd;
            text-transform: uppercase;
        }

        .small {
            font-size: 10px;
            color: #555;
        }

        .resumen {
            background-color: #e8f0fe;
            border-left: 4px solid #0d47a1;
            padding: 12px;
            font-size: 12px;
            color: #333;
            margin-top: 10px;
        }

        .grafica {
            margin: 25px 0;
            text-align: center;
            page-break-inside: avoid;
        }

        .grafica img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ccc;
        }

        .grafica p {
            font-size: 12px;
            color: #0d47a1;
            margin-bottom: 5px;
        }

        .anexo {
            font-size: 11px;
            color: #444;
            border-top: 1px solid #ccc;
            margin-top: 20px;
            padding-top: 10px;
        }

        footer {
            position: fixed;
            bottom: 10px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        .highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ff9800;
            padding: 10px;
            margin-top: 8px;
            font-size: 11px;
        }
    </style>
</head>

<body>
    <!-- Encabezado -->
    <header>
        <img src="{{ public_path('assets/img/TERSIME.png') }}" alt="Logo TERSIME">
        <h1>Informe de Consumo Energético</h1>
        <div class="meta">
            Usuario: {{ $user->name ?? 'N/A' }} —
            Periodo: {{ $fromDate }} a {{ $toDate }}
            @if (!empty($email))
                — Enviado a: {{ $email }}
            @endif
        </div>
    </header>

    <div class="content">
        <!-- Resumen Ejecutivo -->
        <div class="section">
            <h2>Resumen Ejecutivo</h2>
            <div class="resumen">
                {!! $resumen ?? 'No se ha generado un resumen automático.' !!}


            </div>
        </div>

        <!-- Tabla Resumen Global -->
        <div class="section">
            <h2>Resumen de Consumo por Dispositivo</h2>

            @if (!empty($resumenPorDispositivo) && count($resumenPorDispositivo) > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Consumo total (kWh)</th>
                            <th>Consumo medio (kWh/h)</th>
                            <th>Desviación estándar</th>
                            <th>Pico máximo (kWh)</th>
                            <th>Mínimo (kWh)</th>
                            <th>Factor Carga</th>
                            <th>Variación respecto a media histórica (%)</th>
                            <th>% sobre total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resumenPorDispositivo as $row)
                            <tr>
                                <td>{{ $row['nombre'] ?? '–' }}</td>

                                @if (!empty($row['error']))
                                    <td colspan="7" class="small">Error obteniendo datos:
                                        {{ $row['error_message'] ?? 'desconocido' }}</td>
                                @else
                                    <td>{{ number_format($row['total_kwh'] ?? 0, 2, ',', '.') }}</td>
                                    <td>{{ number_format($row['mean_kwh_h'] ?? 0, 3, ',', '.') }}</td>
                                    <td>{{ number_format($row['stddev'] ?? 0, 3, ',', '.') }}</td>
                                    <td>{{ number_format($row['max'] ?? 0, 3, ',', '.') }}</td>
                                    <td>{{ number_format($row['min'] ?? 0, 3, ',', '.') }}</td>
                                    <td>{{ number_format($row['factor_carga'] ?? 0, 3, ',', '.') }}</td>
                                    <td>
                                        @if (is_null($row['variation_percent']))
                                            N/D
                                        @else
                                            {{ number_format($row['variation_percent'], 2, ',', '.') }}%
                                        @endif
                                    </td>
                                    <td>{{ number_format($row['pct_over_total'] ?? 0, 2, ',', '.') }}%</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="small">No hay datos disponibles para el periodo seleccionado.</p>
            @endif
        </div>

        <div class="section">
            <h2>Consumo en el tiempo</h2>
            <div class="grafica">
                <img src="{{ $graficas['tiempo-real'] }}" alt="Consumo por dispositivo">
            </div>
        </div>

        <div class="section">
            <h2>Comparativa histórica</h2>

            @foreach ($dispositivos as $dispositivo)
                @php
                    $nombreDispositivo = $dispositivo->influx_tag;
                    $graficasDispositivo = $graficas[$nombreDispositivo] ?? null;
                @endphp

                <h3>{{ $dispositivo->nombre }}</h3>

                {{-- Media Horaria --}}
                @php
                    $rutaMediaHoraria = $graficasDispositivo['media-horaria'] ?? null;
                    $tituloMediaHoraria = 'Media por Horas en el Periodo Dado';
                @endphp

                @if ($rutaMediaHoraria && file_exists($rutaMediaHoraria))
                    <div class="grafica">
                        <p><strong>{{ $tituloMediaHoraria }}</strong></p>
                        <img src="{{ $rutaMediaHoraria }}" alt="{{ $tituloMediaHoraria }}">
                    </div>
                @else
                    <p class="small"><em>No se encontró la gráfica de {{ $tituloMediaHoraria }}.</em></p>
                @endif

                {{-- Media Horaria Histórico --}}
                @php
                    $rutaMediaHorariaHistorico = $graficasDispositivo['media-horaria-historico'] ?? null;
                    $tituloMediaHorariaHistorico = 'Media por Horas Histórico';
                @endphp

                @if ($rutaMediaHorariaHistorico && file_exists($rutaMediaHorariaHistorico))
                    <div class="grafica">
                        <p><strong>{{ $tituloMediaHorariaHistorico }}</strong></p>
                        <img src="{{ $rutaMediaHorariaHistorico }}" alt="{{ $tituloMediaHorariaHistorico }}">
                    </div>
                @else
                    <p class="small"><em>No se encontró la gráfica de {{ $tituloMediaHorariaHistorico }}.</em></p>
                @endif
            @endforeach
        </div>


        <div class="section">
            <h2>Distribución horaria del consumo</h2>
            <p style="font-size: 13px; line-height: 1.5;">
                {!! nl2br(e($distribucionHorariaTextual)) !!}
            </p>
        </div>

        <div class="section">
            <h2>Análisis de Anomalías</h2>

            @if (!empty($tablaAnomalias) && is_array($tablaAnomalias))
                <table class="tabla">
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Fecha</th>
                            <th>KWh Registrados</th>
                            <th>Media Histórica En Hora</th>
                            <th>Diferencia</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tablaAnomalias as $deviceKey => $lista)
                            @php
                                // Device label seguro
                                $deviceLabel = is_string($deviceKey)
                                    ? $deviceKey
                                    : (string) ($deviceKey ?? 'Desconocido');
                            @endphp

                            @if (empty($lista) || !is_array($lista))
                                <tr>
                                    <td>{{ e($deviceLabel) }}</td>
                                    <td colspan="5" style="text-align:center;">Sin anomalías</td>
                                </tr>
                            @else
                                @foreach ($lista as $fila)
                                    @php
                                        // defensiva: asegurar índices y tipos
                                        $fecha = isset($fila['fecha'])
                                            ? (is_array($fila['fecha'])
                                                ? json_encode($fila['fecha'])
                                                : (string) $fila['fecha'])
                                            : '';
                                        $valor =
                                            isset($fila['valor_kwh']) && is_numeric($fila['valor_kwh'])
                                                ? number_format((float) $fila['valor_kwh'], 3)
                                                : '0.000';
                                        $media =
                                            isset($fila['media_historica_hora_kwh']) &&
                                            is_numeric($fila['media_historica_hora_kwh'])
                                                ? number_format((float) $fila['media_historica_hora_kwh'], 3)
                                                : '0.000';
                                        $exceso =
                                            isset($fila['diferencia_kwh']) && is_numeric($fila['diferencia_kwh'])
                                                ? number_format((float) $fila['diferencia_kwh'], 3)
                                                : '0.000';
                                        $mensaje = $fila['mensaje'] ?? '';
                                        $deviceInRow = $fila['device'] ?? $deviceLabel;
                                    @endphp
                                    <tr>
                                        <td>{{ e($deviceInRow) }}</td>
                                        <td>{{ e($fecha) }}</td>
                                        <td>{{ e($valor) }} kWh</td>
                                        <td>{{ e($media) }} kWh</td>
                                        <td>{{ e($exceso) }} kWh</td>
                                        <td>{{ e($mensaje) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="small">No se detectaron anomalías en el periodo analizado.</p>
            @endif
        </div>


        <div class="section">
            <h2>Coste estimado</h2>

            @if (!empty($costeEstimado) && is_array($costeEstimado))
                <table>
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Consumo total (kWh)</th>
                            <th>Coste estimado (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costeEstimado as $deviceKey => $datos)
                            <tr>
                                <td>{{ $deviceKey }}</td>
                                <td>{{ number_format($datos['consumo_total_kwh'] ?? 0, 3, ',', '.') }}</td>
                                <td>{{ number_format($datos['coste_estimado'] ?? 0, 2, ',', '.') }} €</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="small">No hay datos de coste estimado para este periodo.</p>
            @endif
        </div>

        <!-- Conclusiones (futuro LLM) -->
        <div class="section">
            <h2>Conclusiones y Recomendaciones</h2>
            <div class="highlight" style="font-size: 13px; line-height: 1.5;">
                {{ $conclusion }}
            </div>
        </div>

        <!-- Anexo técnico -->
        <div class="section anexo">
            <h3>Anexo Técnico</h3>
            <p class="small">
                Número de dispositivos analizados: {{ $dispositivos->count() ?? 0 }}<br>
                Fecha de generación del informe: {{ now()->format('Y-m-d H:i:s') }}<br>
                Fuente de datos: Sistema de monitorización TERSIME.
            </p>
        </div>
    </div>

    <!-- Pie -->
    <footer>
        © {{ date('Y') }} TERSIME — Informe generado automáticamente
    </footer>
</body>

</html>
