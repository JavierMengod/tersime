@extends('layouts.plantilla')

@section('title', __('Ajustes'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Ajustes') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Preferencias de la aplicación') }}</p>
    </div>
</div>

@if(session('success_ajustes'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success_ajustes') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('configuracion.ajustes.update') }}">
@csrf

{{-- Apariencia y región ───────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-palette me-1"></i>{{ __('Apariencia y región') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label">{{ __('Tema') }}</label>
                <select name="tema" class="form-select">
                    <option value="light" {{ auth()->user()->tema === 'light' ? 'selected' : '' }}>{{ __('Claro') }}</option>
                    <option value="dark"  {{ auth()->user()->tema === 'dark'  ? 'selected' : '' }}>{{ __('Oscuro') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label">{{ __('Zona horaria') }}</label>
                <select name="zona_horaria" class="form-select">
                    @foreach($zonaHoraria as $value => $label)
                        <option value="{{ $value }}" {{ auth()->user()->zona_horaria === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

{{-- Energía ───────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-lightning-charge me-1"></i>{{ __('Energía') }}
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label">{{ __('Precio del kWh') }}
                    <span class="text-muted fw-normal small">(€)</span>
                </label>
                <div class="input-group">
                    <input type="number" name="coste_kwh" class="form-control"
                           min="0" max="99" step="0.001"
                           value="{{ auth()->user()->coste_kwh ?? 0.15 }}" required>
                    <span class="input-group-text">€/kWh</span>
                </div>
                <div class="form-text">{{ __('Se usa para calcular el coste estimado en el dashboard.') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="mb-5">
    <button type="submit" class="btn btn-primary btn-sm">{{ __('Guardar ajustes') }}</button>
</div>

</form>

@endsection
