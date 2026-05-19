<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Informe de Consumo Energético — TERSIME</title>
  <style>
    @page :first { margin-top: 0; }
    * { box-sizing: border-box; }
    body {
      font-family: DejaVu Sans, sans-serif;
      font-size: 11px;
      color: #1a1a2e;
      margin: 0;
      background: #fff;
      line-height: 1.45;
    }

    /* ── Cabecera ─────────────────────────────────────────────── */
    .page-header {
      background: linear-gradient(135deg, #0d47a1 0%, #1565c0 60%, #1976d2 100%);
      color: white;
      padding: 22px 30px 18px;
    }
    .header-inner {
      display: table;
      width: 100%;
    }
    .header-logo-cell {
      display: table-cell;
      vertical-align: middle;
      width: 90px;
    }
    .header-logo-cell img {
      height: 50px;
    }
    .header-text-cell {
      display: table-cell;
      vertical-align: middle;
      padding-left: 18px;
    }
    .header-title {
      font-size: 20px;
      font-weight: bold;
      letter-spacing: 0.5px;
      margin: 0 0 4px;
    }
    .header-sub {
      font-size: 10px;
      color: rgba(255,255,255,.82);
      margin: 0;
    }
    .header-meta-cell {
      display: table-cell;
      vertical-align: middle;
      text-align: right;
      font-size: 10px;
      color: rgba(255,255,255,.85);
    }

    /* ── Contenido principal ─────────────────────────────────── */
    .content { padding: 22px 28px; }

    /* ── KPI cards ───────────────────────────────────────────── */
    .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 20px; page-break-inside: avoid; }
    .kpi-cell {
      text-align: center;
      padding: 14px 8px;
      border-radius: 6px;
      color: white;
    }
    .kpi-value { font-size: 19px; font-weight: bold; display: block; }
    .kpi-label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .8px; display: block; margin-top: 3px; opacity: .88; }
    .kpi-blue-1 { background: #0d47a1; }
    .kpi-blue-2 { background: #1565c0; }
    .kpi-blue-3 { background: #1976d2; }
    .kpi-green  { background: #2e7d32; }
    .kpi-orange { background: #e65100; }

    /* ── Secciones ───────────────────────────────────────────── */
    h2 {
      font-size: 13px;
      color: #0d47a1;
      border-bottom: 2px solid #0d47a1;
      padding-bottom: 3px;
      margin: 24px 0 10px;
      text-transform: uppercase;
      letter-spacing: .4px;
      page-break-after: avoid;
    }
    h3 {
      font-size: 11px;
      color: #1565c0;
      margin: 16px 0 6px;
      border-left: 3px solid #1976d2;
      padding-left: 8px;
      page-break-after: avoid;
    }
    h4 {
      font-size: 10px;
      color: #37474f;
      margin: 10px 0 4px;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    /* ── Texto ejecutivo ─────────────────────────────────────── */
    .resumen-box {
      background: #e8f0fe;
      border-left: 4px solid #0d47a1;
      padding: 12px 14px;
      font-size: 11px;
      color: #1a237e;
      margin-bottom: 10px;
      text-align: justify;
    }
    .conclusion-box {
      background: #fff8e1;
      border-left: 4px solid #f9a825;
      padding: 12px 14px;
      font-size: 11px;
      color: #33291a;
      text-align: justify;
    }

    /* ── Tablas de datos ─────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 10px; }
    /* Las tablas cortas nunca se parten; la de anomalías puede hacerlo entre filas */
    .table-no-break { page-break-inside: avoid; }
    thead { display: table-header-group; }
    th {
      background: #e3f2fd;
      color: #0d47a1;
      padding: 5px 7px;
      text-align: left;
      font-size: 9.5px;
      text-transform: uppercase;
      letter-spacing: .2px;
      border: 1px solid #b3d4f5;
    }
    td { padding: 5px 7px; border: 1px solid #dce8f5; vertical-align: top; }
    tr:nth-child(even) td { background: #f5f9ff; }

    /* ── Indicadores de variación ─────────────────────────────── */
    .var-pos { color: #c62828; font-weight: bold; }   /* más que histórico = rojo */
    .var-neg { color: #2e7d32; font-weight: bold; }   /* menos que histórico = verde */
    .var-na  { color: #78909c; }

    /* ── Barra de franjas tarifarias ─────────────────────────── */
    .franja-bar-wrap { margin: 4px 0 8px; page-break-inside: avoid; }
    .franja-bar-row  { height: 14px; border-collapse: collapse; width: 100%; }
    .franja-punta { background: #e53935; }
    .franja-llano { background: #fb8c00; }
    .franja-valle { background: #43a047; }
    .franja-legend { font-size: 9px; color: #444; margin-top: 3px; }
    .legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 3px; vertical-align: middle; }

    /* ── Gráficas ─────────────────────────────────────────────── */
    .grafica { margin: 14px 0; text-align: center; }
    .grafica img { max-width: 100%; height: auto; border: 1px solid #cfd8dc; }
    .grafica-caption { font-size: 9.5px; color: #546e7a; margin-bottom: 4px; font-style: italic; }
    .no-grafica { font-size: 10px; color: #78909c; font-style: italic; }

    /* ── Anomalías ────────────────────────────────────────────── */
    .badge-exceso  { background: #ffebee; color: #b71c1c; padding: 1px 5px; border-radius: 3px; font-size: 9px; }
    .badge-defecto { background: #e8f5e9; color: #1b5e20; padding: 1px 5px; border-radius: 3px; font-size: 9px; }

    /* ── Anexo ────────────────────────────────────────────────── */
    .anexo { font-size: 9.5px; color: #546e7a; border-top: 1px solid #cfd8dc; margin-top: 22px; padding-top: 10px; }

    /* ── Texto general justificado ───────────────────────────── */
    p {
      text-align: justify;
      widows: 3;
      orphans: 3;
      margin: 0 0 6px;
    }

    /* ── Separación visual entre secciones de dispositivo ──────── */
    .device-block { margin-top: 18px; }
  </style>
</head>
<body>

{{-- ── CABECERA ─────────────────────────────────────────────────────────────── --}}
<div class="page-header">
  <div class="header-inner">
    <div class="header-logo-cell">
      @if(!empty($logo))
        <img src="{{ $logo }}" alt="TERSIME">
      @endif
    </div>
    <div class="header-text-cell">
      <p class="header-title">Informe de Consumo Energético</p>
      <p class="header-sub">
        Período: {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
        &nbsp;|&nbsp; {{ $resumenGlobal['dias_periodo'] }} días
        &nbsp;|&nbsp; {{ $dispositivos->count() }} dispositivo{{ $dispositivos->count() !== 1 ? 's' : '' }}
      </p>
    </div>
    <div class="header-meta-cell">
      <div>{{ $user->name ?? '' }}</div>
      <div style="margin-top:3px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
  </div>
</div>

<div class="content">

{{-- ── KPI GLOBALES ─────────────────────────────────────────────────────────── --}}
@php
  $varMedia = collect($resumenPorDispositivo)
    ->whereNotNull('variation_percent')
    ->avg('variation_percent');
@endphp
<table class="kpi-table">
  <tr>
    <td class="kpi-cell kpi-blue-1" style="width:20%">
      <span class="kpi-value">{{ number_format($resumenGlobal['total_kwh'], 2, ',', '.') }}</span>
      <span class="kpi-label">kWh consumidos</span>
    </td>
    <td class="kpi-cell kpi-blue-2" style="width:20%">
      <span class="kpi-value">{{ number_format($resumenGlobal['total_coste'], 2, ',', '.') }} €</span>
      <span class="kpi-label">Coste estimado</span>
    </td>
    <td class="kpi-cell kpi-blue-3" style="width:20%">
      <span class="kpi-value">{{ $resumenGlobal['dias_periodo'] }}</span>
      <span class="kpi-label">Días analizados</span>
    </td>
    <td class="kpi-cell {{ $resumenGlobal['total_anomalias'] > 0 ? 'kpi-orange' : 'kpi-green' }}" style="width:20%">
      <span class="kpi-value">{{ $resumenGlobal['total_anomalias'] }}</span>
      <span class="kpi-label">Anomalías detectadas</span>
    </td>
    <td class="kpi-cell {{ !is_null($varMedia) && $varMedia > 5 ? 'kpi-orange' : (!is_null($varMedia) && $varMedia < -5 ? 'kpi-green' : 'kpi-blue-1') }}" style="width:20%">
      <span class="kpi-value">{{ !is_null($varMedia) ? ($varMedia >= 0 ? '+' : '').number_format($varMedia, 1, ',', '.').'%' : 'N/D' }}</span>
      <span class="kpi-label">Variación vs histórico</span>
    </td>
  </tr>
</table>

{{-- ── RESUMEN EJECUTIVO ────────────────────────────────────────────────────── --}}
<h2>Resumen Ejecutivo</h2>
<div class="resumen-box">
  {!! nl2br(e($resumen ?: 'No se ha generado un resumen automático.')) !!}
</div>

{{-- ── ANÁLISIS POR DISPOSITIVO ────────────────────────────────────────────── --}}
<h2>Análisis por Dispositivo</h2>

@foreach($resumenPorDispositivo as $rowIdx => $row)
  @php
    $tag      = $row['device_key'] ?? '';
    $metricas = $metricasAvanzadas[$tag] ?? null;
    $varPct   = $row['variation_percent'] ?? null;
  @endphp

  <div class="device-block">
  <h3>{{ $row['nombre'] ?? $tag }}</h3>

  @if(!empty($row['error']))
    <p style="color:#b71c1c; font-size:10px;"><em>Error al obtener datos de InfluxDB: {{ $row['error_message'] ?? 'desconocido' }}</em></p>
    </div>
    @continue
  @endif

  {{-- Estadísticas principales --}}
  <h4>Estadísticas del período</h4>
  <table class="table-no-break">
    <thead>
      <tr>
        <th>Consumo total</th>
        <th>Media (kWh/h)</th>
        <th>Máximo (kWh/h)</th>
        <th>Mínimo (kWh/h)</th>
        <th>Desviación típica</th>
        <th>Factor carga</th>
        <th>Variación histórica</th>
        <th>% sobre total global</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>{{ number_format($row['total_kwh'] ?? 0, 3, ',', '.') }} kWh</strong></td>
        <td>{{ isset($row['mean_kwh_h']) ? number_format($row['mean_kwh_h'], 4, ',', '.') : '—' }}</td>
        <td>{{ isset($row['max']) ? number_format($row['max'], 4, ',', '.') : '—' }}</td>
        <td>{{ isset($row['min']) ? number_format($row['min'], 4, ',', '.') : '—' }}</td>
        <td>{{ isset($row['stddev']) ? number_format($row['stddev'], 4, ',', '.') : '—' }}</td>
        <td>{{ isset($row['factor_carga']) ? number_format($row['factor_carga'], 4, ',', '.') : '—' }}</td>
        <td class="{{ is_null($varPct) ? 'var-na' : ($varPct > 5 ? 'var-pos' : ($varPct < -5 ? 'var-neg' : '')) }}">
          @if(is_null($varPct)) N/D
          @else {{ $varPct >= 0 ? '+' : '' }}{{ number_format($varPct, 2, ',', '.') }}%
          @endif
        </td>
        <td>{{ number_format($row['pct_over_total'] ?? 0, 2, ',', '.') }}%</td>
      </tr>
    </tbody>
  </table>

  @if($metricas && !empty($metricas['franjas_tarifarias']))
  @php $ft = $metricas['franjas_tarifarias']; @endphp

  {{-- Distribución tarifaria --}}
  <h4 style="margin-top:12px;">Distribución tarifaria 2.0TD</h4>
  <div class="franja-bar-wrap">
    <table class="franja-bar-row" style="height:14px;">
      <tr>
        @if($ft['punta_pct'] > 0)<td class="franja-punta" style="width:{{ $ft['punta_pct'] }}%;"></td>@endif
        @if($ft['llano_pct'] > 0)<td class="franja-llano" style="width:{{ $ft['llano_pct'] }}%;"></td>@endif
        @if($ft['valle_pct'] > 0)<td class="franja-valle" style="width:{{ $ft['valle_pct'] }}%;"></td>@endif
      </tr>
    </table>
    <div class="franja-legend">
      <span class="legend-dot" style="background:#e53935;"></span>Punta {{ $ft['punta_pct'] }}% ({{ number_format($ft['punta_kwh'],2,',','.') }} kWh) &nbsp;
      <span class="legend-dot" style="background:#fb8c00;"></span>Llano {{ $ft['llano_pct'] }}% ({{ number_format($ft['llano_kwh'],2,',','.') }} kWh) &nbsp;
      <span class="legend-dot" style="background:#43a047;"></span>Valle {{ $ft['valle_pct'] }}% ({{ number_format($ft['valle_kwh'],2,',','.') }} kWh)
    </div>
  </div>

  {{-- Patrón semanal + tendencia en la misma fila --}}
  <table class="table-no-break" style="margin-top:6px;">
    <thead>
      <tr>
        <th>Media hora laborable</th>
        <th>Media hora festivo/finde</th>
        <th>Tendencia diaria</th>
        <th>Top pico registrado</th>
      </tr>
    </thead>
    <tbody>
      @php
        $ps      = $metricas['patron_semana'] ?? [];
        $tend    = $metricas['tendencia_kwh_dia'] ?? null;
        $topPico = $metricas['top_picos_horarios'][0] ?? null;
      @endphp
      <tr>
        <td>{{ isset($ps['media_hora_laborable']) ? number_format($ps['media_hora_laborable'],4,',','.') . ' kWh/h' : '—' }}</td>
        <td>{{ isset($ps['media_hora_festivo'])   ? number_format($ps['media_hora_festivo'],  4,',','.') . ' kWh/h' : '—' }}</td>
        <td class="{{ is_null($tend) ? '' : ($tend > 0 ? 'var-pos' : 'var-neg') }}">
          @if(is_null($tend)) Sin datos
          @elseif($tend == 0) Estable
          @else {{ $tend > 0 ? '↑ +' : '↓ ' }}{{ number_format(abs($tend),4,',','.') }} kWh/día
          @endif
        </td>
        <td>{{ $topPico ? $topPico['fecha'] . ' — ' . number_format($topPico['kwh'],3,',','.') . ' kWh' : '—' }}</td>
      </tr>
    </tbody>
  </table>

  @if(!empty($metricas['top_dias_consumo']))
  <h4 style="margin-top:10px;">Días con mayor consumo</h4>
  <table class="table-no-break">
    <thead>
      <tr>
        @foreach($metricas['top_dias_consumo'] as $fecha => $kwh)
          <th style="text-align:center;">{{ $fecha }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      <tr>
        @foreach($metricas['top_dias_consumo'] as $fecha => $kwh)
          <td style="text-align:center;">{{ number_format($kwh,3,',','.') }} kWh</td>
        @endforeach
      </tr>
    </tbody>
  </table>
  @endif

  @if(!empty($metricas['patron_dia_semana']))
  @php
    $pds     = $metricas['patron_dia_semana'];
    $pdVals  = array_filter(array_values($pds), fn($v) => $v !== null);
    $pdMax   = !empty($pdVals) ? max($pdVals) : 1;
    $pdMax   = $pdMax ?: 1;
  @endphp
  <h4 style="margin-top:10px;">Distribución media por día de la semana (kWh/h)</h4>
  <table style="width:100%; border-collapse:collapse; margin:4px 0 8px; page-break-inside:avoid; font-size:9px;">
    <thead>
      <tr>
        <th style="width:11%; text-align:left; background:#e3f2fd; color:#0d47a1; padding:4px 6px; border:1px solid #b3d4f5; text-transform:uppercase;">Día</th>
        <th style="width:66%; text-align:left; background:#e3f2fd; color:#0d47a1; padding:4px 6px; border:1px solid #b3d4f5; text-transform:uppercase;">Perfil</th>
        <th style="width:23%; text-align:right; background:#e3f2fd; color:#0d47a1; padding:4px 6px; border:1px solid #b3d4f5; text-transform:uppercase;">Media kWh/h</th>
      </tr>
    </thead>
    <tbody>
      @foreach($pds as $dia => $kwh)
        @php
          $barPct = $kwh !== null ? min(100, round($kwh / $pdMax * 100, 1)) : 0;
          $isWknd = in_array($dia, ['Sáb', 'Dom']);
          $barClr = $isWknd ? '#7986cb' : '#1976d2';
        @endphp
        <tr style="background:{{ $isWknd ? '#ede7f6' : ($loop->iteration % 2 === 0 ? '#f5f9ff' : '#fff') }};">
          <td style="padding:3px 6px; border:1px solid #dce8f5; font-weight:{{ $isWknd ? 'bold' : 'normal' }}; color:{{ $isWknd ? '#4527a0' : '#1a1a2e' }};">{{ $dia }}</td>
          <td style="padding:2px 4px; border:1px solid #dce8f5;">
            <table style="width:100%; border-collapse:collapse; height:9px;">
              <tr>
                @if($barPct > 0)<td style="width:{{ $barPct }}%; background:{{ $barClr }};"></td>@endif
                @if($barPct < 100)<td style="width:{{ 100 - $barPct }}%;"></td>@endif
              </tr>
            </table>
          </td>
          <td style="padding:3px 6px; border:1px solid #dce8f5; text-align:right;">{{ $kwh !== null ? number_format($kwh, 4, ',', '.') : '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p style="font-size:8.5px; color:#78909c; margin:0 0 6px;">Laborables en azul. Fin de semana en morado. Barras proporcionales al máximo del período.</p>
  @endif

  @endif {{-- fin if metricas --}}
  </div>{{-- /device block --}}
@endforeach

{{-- ── GRÁFICAS ─────────────────────────────────────────────────────────────── --}}
<h2>Evolución del Consumo en el Tiempo</h2>
@if(!empty($graficas['tiempo-real']))
  <div class="grafica">
    <p class="grafica-caption">Consumo por dispositivo durante el período seleccionado</p>
    <img src="{{ $graficas['tiempo-real'] }}" alt="Consumo temporal">
  </div>
@else
  <p class="no-grafica">No se pudo cargar la gráfica de evolución temporal.</p>
@endif

@if(!empty($graficas['consumo-diario']))
  <div class="grafica">
    <p class="grafica-caption">Consumo diario por dispositivo (barras agregadas)</p>
    <img src="{{ $graficas['consumo-diario'] }}" alt="Consumo diario">
  </div>
@endif

<h2>Distribución Horaria — Media del Período vs Histórico</h2>
@foreach($dispositivos as $d)
  @php $tag = $d->influx_tag ?: $d->nombre; @endphp
  <h3>{{ $d->nombre }}</h3>

  @php $rutaHoraria = $graficas[$tag]['media-horaria'] ?? null; @endphp
  @if($rutaHoraria)
    <div class="grafica">
      <p class="grafica-caption">Media horaria en el período analizado</p>
      <img src="{{ $rutaHoraria }}" alt="Media horaria {{ $d->nombre }}">
    </div>
  @else
    <p class="no-grafica">No disponible — media horaria del período.</p>
  @endif

  @php $rutaHist = $graficas[$tag]['media-horaria-historico'] ?? null; @endphp
  @if($rutaHist)
    <div class="grafica">
      <p class="grafica-caption">Media horaria histórica (referencia 2 años)</p>
      <img src="{{ $rutaHist }}" alt="Media histórica {{ $d->nombre }}">
    </div>
  @else
    <p class="no-grafica">No disponible — media histórica.</p>
  @endif
@endforeach

{{-- ── ANÁLISIS DE DISTRIBUCIÓN HORARIA (LLM) ─────────────────────────────── --}}
<h2>Análisis de la Distribución Horaria</h2>
<div style="font-size:11px; line-height:1.65; text-align:justify;">
  {!! nl2br(e($distribucionHorariaTextual ?: 'No disponible.')) !!}
</div>

{{-- ── ANOMALÍAS ────────────────────────────────────────────────────────────── --}}
<h2>Análisis de Anomalías</h2>
@php
  $hayAnomalias = false;
  foreach($tablaAnomalias as $lista) {
    if(!empty($lista)) { $hayAnomalias = true; break; }
  }
  $tagToNombre = $dispositivos->keyBy(fn($d) => $d->influx_tag ?: $d->nombre)->map->nombre;
@endphp

@if($hayAnomalias)
  @foreach($tablaAnomalias as $deviceKey => $lista)
    @if(empty($lista)) @continue @endif
    @php
      usort($lista, function($a, $b) {
        $mediaA = max(0.0001, (float)($a['media_historica_hora_kwh'] ?? 0));
        $mediaB = max(0.0001, (float)($b['media_historica_hora_kwh'] ?? 0));
        return ($b['diferencia_kwh'] / $mediaB) <=> ($a['diferencia_kwh'] / $mediaA);
      });
      $mostrar   = array_slice($lista, 0, 20);
      $nTotal    = count($lista);
      $nExcesos  = count(array_filter($lista, fn($a) => $a['tipo'] === 'exceso'));
      $nDefectos = $nTotal - $nExcesos;
      $nombreDispositivo = $tagToNombre[$deviceKey] ?? $deviceKey;
    @endphp

    <h3>{{ $nombreDispositivo }} — {{ $nTotal }} anomalía{{ $nTotal !== 1 ? 's' : '' }}
      <span style="font-size:9px; font-weight:normal; color:#555;">
        ({{ $nExcesos }} excesos, {{ $nDefectos }} defectos{{ $nTotal > 20 ? ' — mostrando las 20 más severas' : '' }})
      </span>
    </h3>
    <table>
      <thead>
        <tr>
          <th>Tipo</th>
          <th>Fecha y hora</th>
          <th>Registrado (kWh)</th>
          <th>Media histórica hora (kWh)</th>
          <th>Diferencia (kWh)</th>
          <th>Desviación</th>
        </tr>
      </thead>
      <tbody>
        @foreach($mostrar as $a)
          @php
            $diferencia = (float)($a['diferencia_kwh'] ?? 0);
            $media      = (float)($a['media_historica_hora_kwh'] ?? 0);
            $desv       = $media > 0 ? round($diferencia / $media * 100, 1) : null;
          @endphp
          <tr style="page-break-inside:avoid;">
            <td>
              @if($a['tipo'] === 'exceso')
                <span class="badge-exceso">Exceso</span>
              @else
                <span class="badge-defecto">Defecto</span>
              @endif
            </td>
            <td>{{ is_string($a['fecha']) ? \Carbon\Carbon::parse($a['fecha'])->setTimezone('Europe/Madrid')->format('d/m/Y H:i') : '—' }}</td>
            <td>{{ number_format((float)($a['valor_kwh'] ?? 0), 4, ',', '.') }}</td>
            <td>{{ number_format((float)($a['media_historica_hora_kwh'] ?? 0), 4, ',', '.') }}</td>
            <td>{{ number_format($diferencia, 4, ',', '.') }}</td>
            <td>{{ !is_null($desv) ? ($a['tipo'] === 'exceso' ? '+' : '-').number_format(abs($desv),1,',','.').'%' : '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endforeach
@else
  <p style="color:#2e7d32; font-size:10px;">No se detectaron anomalías en el período analizado con los umbrales configurados.</p>
@endif

{{-- ── COSTE ESTIMADO ───────────────────────────────────────────────────────── --}}
<h2>Desglose de Costes Estimados</h2>
@if(!empty($costeEstimado))
  @php $precioKwh = config('tersime.costes.kwh', 0.15); @endphp
  <table class="table-no-break">
    <thead>
      <tr>
        <th>Dispositivo</th>
        <th>Consumo total (kWh)</th>
        <th>Precio unitario (€/kWh)</th>
        <th>Coste estimado (€)</th>
        <th>% sobre coste total</th>
      </tr>
    </thead>
    <tbody>
      @php $costoTotal = array_sum(array_column($costeEstimado, 'coste_estimado')); @endphp
      @foreach($costeEstimado as $deviceKey => $c)
        <tr>
          <td>{{ $tagToNombre[$deviceKey] ?? $deviceKey }}</td>
          <td>{{ number_format($c['consumo_total_kwh'] ?? 0, 3, ',', '.') }}</td>
          <td>{{ number_format($precioKwh, 4, ',', '.') }}</td>
          <td><strong>{{ number_format($c['coste_estimado'] ?? 0, 2, ',', '.') }} €</strong></td>
          <td>{{ $costoTotal > 0 ? number_format(($c['coste_estimado'] / $costoTotal) * 100, 1, ',', '.') . '%' : '—' }}</td>
        </tr>
      @endforeach
      @if(count($costeEstimado) > 1)
        <tr style="background:#e3f2fd;">
          <td colspan="3"><strong>TOTAL</strong></td>
          <td><strong>{{ number_format($costoTotal, 2, ',', '.') }} €</strong></td>
          <td>100%</td>
        </tr>
      @endif
    </tbody>
  </table>
  <p class="small" style="font-size:9px; color:#78909c; margin-top:4px;">
    Precio unitario aplicado: {{ number_format($precioKwh, 4, ',', '.') }} €/kWh. Coste orientativo, no incluye impuestos ni peajes de red.
  </p>
@else
  <p style="font-size:10px; color:#78909c;"><em>No hay datos de coste para este período.</em></p>
@endif

{{-- ── CONCLUSIONES ─────────────────────────────────────────────────────────── --}}
<h2>Conclusiones y Recomendaciones</h2>
<div class="conclusion-box">
  {!! nl2br(e($conclusion ?: 'No disponible.')) !!}
</div>

{{-- ── ANEXO TÉCNICO ────────────────────────────────────────────────────────── --}}
<div class="anexo">
  <strong>Anexo técnico</strong><br>
  Período analizado: {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
  ({{ $resumenGlobal['dias_periodo'] }} días) &nbsp;|&nbsp;
  Dispositivos: {{ $dispositivos->count() }} &nbsp;|&nbsp;
  Generado: {{ now()->format('Y-m-d H:i:s') }} &nbsp;|&nbsp;
  Sistema: TERSIME
</div>

</div>{{-- /content --}}
</body>
</html>
