@extends('layouts.plantilla')

@section('title', __('Conexiones'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Conexiones') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Servicios externos — sobrescriben los valores del archivo .env') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('configuracion.conexiones.update') }}">
@csrf

{{-- InfluxDB ────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-database me-1"></i>InfluxDB
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">URL</label>
                <input type="text" name="influxdb_url" class="form-control font-monospace"
                       value="{{ $configuracion['influxdb_url'] }}" placeholder="http://servidor:8086" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">{{ __('Organización') }}</label>
                <input type="text" name="influxdb_org" class="form-control"
                       value="{{ $configuracion['influxdb_org'] }}" placeholder="tersime" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">Bucket</label>
                <input type="text" name="influxdb_bucket" class="form-control"
                       value="{{ $configuracion['influxdb_bucket'] }}" placeholder="PINZAS" required>
            </div>
            <div class="col-12">
                <label class="form-label">Token
                    @if($tieneInfluxToken)
                        <span class="badge bg-success ms-1">{{ __('Configurado') }}</span>
                    @else
                        <span class="badge bg-warning text-dark ms-1">{{ __('No configurado') }}</span>
                    @endif
                </label>
                <input type="password" name="influxdb_token" class="form-control font-monospace"
                       autocomplete="new-password"
                       placeholder="{{ $tieneInfluxToken ? __('Dejar vacío para no cambiar') : __('Token de acceso a InfluxDB') }}">
            </div>
        </div>
    </div>
</div>

{{-- Grafana ─────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-bar-chart-line me-1"></i>Grafana
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">{{ __('URL base') }}</label>
                <input type="text" name="grafana_base_url" class="form-control font-monospace"
                       value="{{ $configuracion['grafana_base_url'] }}" placeholder="http://servidor:3000" required>
                <div class="form-text">{{ __('Usada para consultas de datos y capturas de paneles') }}</div>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">{{ __('ID de datasource') }}</label>
                <input type="number" name="grafana_datasource_id" class="form-control"
                       value="{{ $configuracion['grafana_datasource_id'] }}" min="1" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">{{ __('URL del renderer') }}</label>
                <input type="text" name="grafana_renderer_url" class="form-control font-monospace"
                       value="{{ $configuracion['grafana_renderer_url'] }}" placeholder="http://localhost:8081/render">
                <div class="form-text">{{ __('Servicio de renderizado de imágenes (puerto 8081)') }}</div>
            </div>
            <div class="col-12">
                <label class="form-label">API Key
                    @if($tieneGrafanaKey)
                        <span class="badge bg-success ms-1">{{ __('Configurada') }}</span>
                    @else
                        <span class="badge bg-warning text-dark ms-1">{{ __('No configurada') }}</span>
                    @endif
                </label>
                <input type="password" name="grafana_api_key" class="form-control font-monospace"
                       autocomplete="new-password"
                       placeholder="{{ $tieneGrafanaKey ? __('Dejar vacío para no cambiar') : __('Service account token o API key') }}">
            </div>
        </div>
    </div>
</div>

{{-- Predictor ───────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-graph-up-arrow me-1"></i>{{ __('Predictor (Prophet)') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">URL</label>
                <input type="text" name="predictor_url" class="form-control font-monospace"
                       value="{{ $configuracion['predictor_url'] }}" placeholder="http://localhost:5000/predict">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">{{ __('Timeout') }}
                    <span class="text-muted fw-normal small">({{ __('segundos') }})</span>
                </label>
                <input type="number" name="predictor_timeout" class="form-control"
                       value="{{ $configuracion['predictor_timeout'] }}" min="5" max="600" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label">{{ __('Horas de predicción por defecto') }}</label>
                <input type="number" name="predictor_default_hours" class="form-control"
                       value="{{ $configuracion['predictor_default_hours'] }}" min="1" max="168" required>
            </div>
        </div>
    </div>
</div>

{{-- OpenRouter (LLM) ─────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-cpu me-1"></i>{{ __('LLM (OpenRouter)') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="form-label">{{ __('Modelo') }}</label>
                <input type="text" name="openrouter_model" class="form-control font-monospace"
                       value="{{ $configuracion['openrouter_model'] }}"
                       placeholder="openai/gpt-4o-mini">
                <div class="form-text">{{ __('Identificador del modelo en OpenRouter (ej: openai/gpt-4o-mini, mistralai/mistral-7b-instruct:free)') }}</div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label">API Key
                    @if($tieneOpenRouterKey)
                        <span class="badge bg-success ms-1">{{ __('Configurada') }}</span>
                    @else
                        <span class="badge bg-warning text-dark ms-1">{{ __('No configurada') }}</span>
                    @endif
                </label>
                <input type="password" name="openrouter_api_key" class="form-control font-monospace"
                       autocomplete="new-password"
                       placeholder="{{ $tieneOpenRouterKey ? __('Dejar vacío para no cambiar') : 'sk-or-v1-...' }}">
            </div>
        </div>
    </div>
</div>

<div>
    <button type="submit" class="btn btn-primary">{{ __('Guardar conexiones') }}</button>
</div>

</form>

@endsection
