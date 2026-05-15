@extends('layouts.plantilla')

@section('title', __('Configuración del sistema'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Configuración del sistema') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Solo visible para administradores') }}</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('configuracion.sistema.update') }}">
@csrf

{{-- Retención de datos ──────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-clock-history me-1"></i>{{ __('Retención de datos') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label">{{ __('Historial de alertas') }}
                    <span class="text-muted fw-normal small">({{ __('días') }})</span>
                </label>
                <input type="number" name="alert_log_retention_days" class="form-control"
                       min="1" max="3650" value="{{ $settings['alert_log_retention_days'] }}" required>
                <div class="form-text">{{ $stats['alert_logs_total'] }} {{ __('registros actualmente en base de datos') }}</div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">{{ __('Informes generados') }}
                    <span class="text-muted fw-normal small">({{ __('días') }})</span>
                </label>
                <input type="number" name="report_retention_days" class="form-control"
                       min="1" max="3650" value="{{ $settings['report_retention_days'] }}" required>
                <div class="form-text">{{ $stats['reports_total'] }} {{ __('informes actualmente almacenados') }}</div>
            </div>
        </div>
    </div>
</div>


<div class="mb-5">
    <button type="submit" class="btn btn-primary">{{ __('Guardar configuración') }}</button>
</div>

</form>

{{-- Mantenimiento ───────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm border-danger-subtle">
    <div class="card-header bg-transparent fw-semibold text-danger">
        <i class="bi bi-tools me-1"></i>{{ __('Mantenimiento') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold small">{{ __('Purgar historial de alertas') }}</div>
                        <div class="text-muted small">
                            {{ __('Elimina registros con más de') }}
                            <strong>{{ $settings['alert_log_retention_days'] }}</strong>
                            {{ __('días') }} · {{ $stats['alert_logs_total'] }} {{ __('registros') }}
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm ms-3 flex-shrink-0"
                            data-bs-toggle="modal" data-bs-target="#modal-purgar-alertas">
                        {{ __('Purgar ahora') }}
                    </button>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold small">{{ __('Purgar informes antiguos') }}</div>
                        <div class="text-muted small">
                            {{ __('Elimina PDFs con más de') }}
                            <strong>{{ $settings['report_retention_days'] }}</strong>
                            {{ __('días') }} · {{ $stats['reports_total'] }} {{ __('informes') }}
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm ms-3 flex-shrink-0"
                            data-bs-toggle="modal" data-bs-target="#modal-purgar-informes">
                        {{ __('Purgar ahora') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Modal purgar alertas --}}
<div class="modal fade" id="modal-purgar-alertas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i>{{ __('Purgar alertas') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('Se eliminarán') }} <strong>{{ $stats['alert_logs_total'] }}</strong> {{ __('registros de alerta con más de') }}
                <strong>{{ $settings['alert_log_retention_days'] }} {{ __('días') }}</strong>.
                {{ __('Esta acción no se puede deshacer.') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <form method="POST" action="{{ route('configuracion.sistema.purgar_alertas') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Purgar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal purgar informes --}}
<div class="modal fade" id="modal-purgar-informes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i>{{ __('Purgar informes') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('Se eliminarán') }} <strong>{{ $stats['reports_total'] }}</strong> {{ __('informes con más de') }}
                <strong>{{ $settings['report_retention_days'] }} {{ __('días') }}</strong>,
                {{ __('incluyendo sus archivos PDF.') }}
                {{ __('Esta acción no se puede deshacer.') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <form method="POST" action="{{ route('configuracion.sistema.purgar_informes') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Purgar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
