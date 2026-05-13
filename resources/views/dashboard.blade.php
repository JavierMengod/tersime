@extends('layouts.plantilla')

@section('title', __('Dashboard'))

@section('contenido')
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">{{ __('Dashboard') }}</h2>
        </div>

        <div class="container-fluid px-2">

            @php
                use Carbon\Carbon;

                $deviceParams = collect($dispositivos ?? [])->map(function ($d) {
                    return 'var-dispositivos=' . rawurlencode($d->URL);
                })->implode('&');

                $deviceQuery = $deviceParams ? '&' . $deviceParams : '';

                $grafanaTheme   = Auth::user()->theme ?? 'light';
                $grafanaBase    = config('app.grafana_base_url') . '/d-solo/fek5yx516oyrkd/dashboard-principal';
                $grafanaBaseAlt = config('app.grafana_base_url') . '/d-solo/eegznxsjl47i8b/dashboard-initiot';

                $now          = Carbon::now('Europe/Madrid');
                $toNowMs      = $now->getTimestamp() * 1000;
                $fromYearMs   = $now->copy()->subYear()->getTimestamp() * 1000;
                $from30DaysMs = $now->copy()->subDays(30)->getTimestamp() * 1000;
                $from7DaysMs  = $now->copy()->subDays(7)->getTimestamp() * 1000;

                $defaultFrom  = $from30DaysMs;
                $defaultTo    = $toNowMs;
            @endphp

            {{-- Fila 1 --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo anormalmente alto') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 5,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo anormalmente bajo') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 6,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fila 2 --}}
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('TOP 5 Dispositivos') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 2,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="340" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fila 3 --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Factor de carga') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 11,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Actividad de dispositivos') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 10,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fila 4 --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Horas activas por semana') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 9,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo total semanal') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $defaultFrom, 'to' => $defaultTo,
                                    'timezone' => 'browser', 'panelId' => 8,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fila 5 --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Desviación media (últimos 7 días)') }}</div>
                        <div class="card-body p-0">
                            @php
                                $src = $grafanaBase . '?' . http_build_query([
                                    'orgId' => 1, 'from' => $from7DaysMs, 'to' => $toNowMs,
                                    'timezone' => 'browser', 'panelId' => 4,
                                    '__feature.dashboardSceneSolo' => 'true', 'theme' => $grafanaTheme,
                                ], '', '&', PHP_QUERY_RFC3986) . $deviceQuery;
                            @endphp
                            <iframe src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Consumo medio diario') }}</div>
                        <div class="card-body p-0">
                            @php
                                $fixed = http_build_query([
                                    'orgId' => 1, 'from' => $from30DaysMs, 'to' => $toNowMs,
                                    'timezone' => 'browser', 'panelId' => 3, 'theme' => $grafanaTheme,
                                    '__feature.dashboardSceneSolo' => 'true',
                                ], '', '&', PHP_QUERY_RFC3986);
                                $src = $grafanaBaseAlt . '?' . $fixed . $deviceQuery;
                            @endphp
                            <iframe id="grafanaIframe" src="{{ $src }}" width="100%" height="300" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fila 6 --}}
            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-white fw-bold">{{ __('Variación mensual (último año)') }}</div>
                        <div class="card-body p-0">
                            @php
                                $fixedVar = http_build_query([
                                    'orgId' => 1, 'from' => $fromYearMs, 'to' => $toNowMs,
                                    'timezone' => 'browser', 'panelId' => 7, 'theme' => $grafanaTheme,
                                    '__feature.dashboardSceneSolo' => 'true',
                                ], '', '&', PHP_QUERY_RFC3986);
                                $srcVar = $grafanaBase . '?' . $fixedVar . $deviceQuery;
                            @endphp
                            <iframe src="{{ $srcVar }}" width="100%" height="360" frameborder="0"></iframe>
                        </div>
                    </div>
                </div>
            </div>

        </div>
@endsection
