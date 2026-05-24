@extends('layouts.plantilla')

@section('title', __('Perfil'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Perfil') }}</h2>
        <p class="text-muted mb-0 small">{{ auth()->user()->email }}</p>
    </div>
</div>

@if(session('success_perfil'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success_perfil') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('success_password'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success_password') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Datos personales ───────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-person me-1"></i>{{ __('Datos personales') }}
    </div>
    <div class="card-body">
        @if($errors->hasAny(['nombre', 'email', 'idioma']))
            <div class="alert alert-danger py-2 small">
                <ul class="mb-0 ps-3">
                    @foreach(['nombre','email','idioma'] as $field)
                        @error($field)<li>{{ $message }}</li>@enderror
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('configuracion.perfil.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Nombre') }}</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror"
                           value="{{ old('nombre', auth()->user()->nombre) }}" required maxlength="255">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Correo electrónico') }}</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', auth()->user()->email) }}" required maxlength="255">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">{{ __('Idioma') }}</label>
                    <select name="idioma" class="form-select">
                        <option value="es" {{ auth()->user()->idioma === 'es' ? 'selected' : '' }}>{{ __('Español') }}</option>
                        <option value="en" {{ auth()->user()->idioma === 'en' ? 'selected' : '' }}>{{ __('Inglés') }}</option>
                        <option value="fr" {{ auth()->user()->idioma === 'fr' ? 'selected' : '' }}>{{ __('Francés') }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Guardar perfil') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Cambiar contraseña ───────────────────────────────────────────────────── --}}
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

        <form method="POST" action="{{ route('configuracion.perfil.password') }}">
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
