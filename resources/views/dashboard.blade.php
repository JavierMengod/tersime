@extends('layouts.plantilla')

@section('title', __('Dashboard'))

@section('contenido')
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">{{ __('Dashboard') }}</h2>
            {{-- Selector de período --}}
            <div class="btn-group" role="group" id="period-selector">
                <button class="btn btn-sm btn-outline-secondary" data-days="1">24 h</button>
                <button class="btn btn-sm btn-outline-secondary" data-days="7">7 días</button>
                <button class="btn btn-sm btn-outline-secondary active" data-days="30">30 días</button>
                <button class="btn btn-sm btn-outline-secondary" data-days="365">Año</button>
            </div>
        </div>

        <div class="container-fluid px-2">

            @php
                use Carbon\Carbon;
                use App\Models\Ajuste;

                $dispositivos   = $dispositivos ?? collect([]);
                $deviceParams   = $dispositivos->map(fn($d) => 'var-dispositivos=' . rawurlencode($d->influx_tag))->implode('&');
                $deviceQuery    = $deviceParams ? '&' . $deviceParams : '';
                $costeKwh       = auth()->user()->coste_kwh ?? '0.15';
                $deviceQuery   .= '&var-coste_kwh=' . $costeKwh;

                $grafanaTheme   = Auth::user()->theme ?? 'light';
                $grafanaBase    = '/grafana/d-solo/fek5yx516oyrkd/dashboard-principal';
                $grafanaBaseAlt = '/grafana/d-solo/eegznxsjl47i8b/dashboard-initiot';

                $now          = Carbon::now('Europe/Madrid');
                $toNowMs      = $now->getTimestamp() * 1000;
                $from30DaysMs = $now->copy()->subDays(30)->getTimestamp() * 1000;
                $from7DaysMs  = $now->copy()->subDays(7)->getTimestamp() * 1000;
                $from1YearMs  = $now->copy()->subYear()->getTimestamp() * 1000;

                $defaultFrom  = $from30DaysMs;
                $defaultTo    = $toNowMs;

                $commonParams = fn(int $panelId, int $from, int $to) =>
                    http_build_query([
                        'orgId'                         => 1,
                        'from'                          => $from,
                        'to'                            => $to,
                        'timezone'                      => 'browser',
                        'panelId'                       => $panelId,
                        '__feature.dashboardSceneSolo'  => 'true',
                        'theme'                         => $grafanaTheme,
                    ], '', '&', PHP_QUERY_RFC3986);
            @endphp

            {{-- ═══ Fila KPI: 4 stat panels ══════════════════════════════════════════ --}}
            {{-- Paneles 20-22 usan grafana-range → siguen el selector de período.   --}}
            {{-- Panel 23 usa grafana-kpi → siempre muestra actividad en última 1 h. --}}
            <div class="row g-3 mb-3">
                @php
                    $statPanels = [
                        20 => __('Consumo del período'),
                        21 => __('Media diaria del período'),
                        22 => __('Coste del período'),
                    ];
                @endphp
                @foreach($statPanels as $pid => $title)
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold small text-muted py-1 d-flex align-items-center" style="min-height:3.5rem">{{ $title }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams($pid, $defaultFrom, $defaultTo) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="130" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
                @endforeach
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-semibold small text-muted py-1 d-flex align-items-center" style="min-height:3.5rem">{{ __('Dispositivos activos ahora') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(23, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="130" frameborder="0"
                                    class="grafana-kpi"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Último dato por dispositivo ══════════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Estado de dispositivos') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . $commonParams(24, $from30DaysMs, $toNowMs) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="260" frameborder="0"
                                    class="grafana-kpi"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Anomalías ══════════════════════════════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Por Encima de la Media (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(5, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Por Debajo de la Media (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(6, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ TOP 5 + Consumo medio ══════════════════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Top 5 Dispositivos por Consumo') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(2, $defaultFrom, $defaultTo) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo Medio Diario por Dispositivo') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBaseAlt . '?' . $commonParams(3, $defaultFrom, $defaultTo) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Factor carga + Actividad ═══════════════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Factor de Carga por Dispositivo') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(11, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Último Valor por Dispositivo') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(10, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Horas activas + Consumo semanal ═══════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Horas Activas (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(9, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo Total (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(8, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Desviación media ════════════════════════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Desviación del Consumo (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(4, $from7DaysMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"
                                    class="grafana-range"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══ Variación mensual (año completo) ═══════════════════════════════════ --}}
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Variación de Consumo Mensual') }}</div>
                        <div class="card-body p-0">
                            @php $src = $grafanaBase . '?' . $commonParams(7, $from1YearMs, $toNowMs) . $deviceQuery; @endphp
                            <iframe src="{{ $src }}" width="100%" height="360" frameborder="0"
                                    class="grafana-kpi"></iframe>
                        </div>
                    </div>
                </div>
            </div>

        </div>

@push('scripts')
<script>
    window.DASHBOARD_TO_MS = {{ $toNowMs }};
</script>
<script src="{{ asset('assets/js/dashboard.js') }}"></script>
@endpush
@endsection
