@extends('layouts.plantilla')

@section('title', __('Mi cuenta'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Mi cuenta') }}</h2>
        <p class="text-muted mb-0 small">{{ auth()->user()->name }}</p>
    </div>
</div>

@if(session('success_prefs'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success_prefs') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('success_password'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success_password') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Preferencias ──────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-sliders me-1"></i>{{ __('Preferencias') }}
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('configuracion.cuenta.preferencias') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Idioma') }}</label>
                    <select name="language" class="form-select">
                        <option value="es" {{ auth()->user()->language === 'es' ? 'selected' : '' }}>{{ __('Español') }}</option>
                        <option value="en" {{ auth()->user()->language === 'en' ? 'selected' : '' }}>{{ __('Inglés') }}</option>
                        <option value="fr" {{ auth()->user()->language === 'fr' ? 'selected' : '' }}>{{ __('Francés') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Tema') }}</label>
                    <select name="theme" class="form-select">
                        <option value="light" {{ auth()->user()->theme === 'light' ? 'selected' : '' }}>{{ __('Claro') }}</option>
                        <option value="dark"  {{ auth()->user()->theme === 'dark'  ? 'selected' : '' }}>{{ __('Oscuro') }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Zona horaria') }}</label>
                    <select name="timezone" class="form-select">
                        @foreach($timezones as $value => $label)
                            <option value="{{ $value }}" {{ auth()->user()->timezone === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Guardar preferencias') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Cambio de contraseña ─────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-lock me-1"></i>{{ __('Cambiar contraseña') }}
    </div>
    <div class="card-body">
        @if($errors->has('current_password'))
            <div class="alert alert-danger py-2 small">{{ $errors->first('current_password') }}</div>
        @endif
        @if($errors->has('new_password'))
            <div class="alert alert-danger py-2 small">{{ $errors->first('new_password') }}</div>
        @endif

        <form method="POST" action="{{ route('configuracion.cuenta.password') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Contraseña actual') }}</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Nueva contraseña') }}</label>
                    <input type="password" name="new_password" class="form-control" required autocomplete="new-password" minlength="6">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Confirmar nueva contraseña') }}</label>
                    <input type="password" name="new_password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Cambiar contraseña') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection
