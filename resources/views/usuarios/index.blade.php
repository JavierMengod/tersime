@extends('layouts.plantilla')

@section('title', __('Gestión de Usuarios'))

@section('contenido')

@php
    $openCreate = $errors->has('name') || $errors->has('password') || $errors->has('password_confirmation');
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Gestión de Usuarios') }}</h2>
        <p class="text-muted mb-0 small">{{ $usuarios->total() }} {{ __('usuarios registrados') }}</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear">
        <i class="bi bi-person-plus me-1"></i>{{ __('Nuevo usuario') }}
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Usuario') }}</th>
                        <th>{{ __('Idioma') }}</th>
                        <th>{{ __('Zona horaria') }}</th>
                        <th>{{ __('Tema') }}</th>
                        <th>{{ __('Rol') }}</th>
                        <th>{{ __('Estado') }}</th>
                        <th>{{ __('Creado') }}</th>
                        <th>{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($usuarios as $usuario)
                    <tr class="{{ !$usuario->enabled ? 'text-muted' : '' }}">
                        <td class="fw-semibold">{{ $usuario->name }}</td>

                        <td>{{ strtoupper($usuario->language ?? 'es') }}</td>

                        <td class="text-nowrap">{{ $usuario->timezone ?? 'Europe/Madrid' }}</td>

                        <td>
                            @if($usuario->theme === 'dark')
                                <span class="badge bg-dark"><i class="bi bi-moon-fill me-1"></i>{{ __('Oscuro') }}</span>
                            @else
                                <span class="badge bg-secondary"><i class="bi bi-sun-fill me-1"></i>{{ __('Claro') }}</span>
                            @endif
                        </td>

                        <td>
                            @if($usuario->admin)
                                <span class="badge bg-warning text-dark">{{ __('Admin') }}</span>
                            @else
                                <span class="badge bg-light text-secondary border">{{ __('Usuario') }}</span>
                            @endif
                        </td>

                        <td>
                            @if($usuario->enabled)
                                <span class="badge bg-success">{{ __('Activo') }}</span>
                            @else
                                <span class="badge bg-danger">{{ __('Deshabilitado') }}</span>
                            @endif
                        </td>

                        <td class="text-muted text-nowrap">
                            {{ $usuario->created_at ? $usuario->created_at->format('d/m/y') : '—' }}
                        </td>

                        <td>
                            <div class="d-flex gap-1">
                                {{-- Habilitar / Deshabilitar --}}
                                @if($usuario->id !== auth()->id())
                                    <form action="{{ route('usuarios.toggle', $usuario) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="btn btn-sm py-0 px-2 {{ $usuario->enabled ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $usuario->enabled ? __('Deshabilitar') : __('Habilitar') }}">
                                            {{ $usuario->enabled ? __('Deshabilitar') : __('Habilitar') }}
                                        </button>
                                    </form>
                                @endif

                                {{-- Editar --}}
                                <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modal-editar"
                                        data-user="{{ json_encode([
                                            'id'       => $usuario->id,
                                            'name'     => $usuario->name,
                                            'language' => $usuario->language ?? 'es',
                                            'timezone' => $usuario->timezone ?? 'Europe/Madrid',
                                            'theme'    => $usuario->theme ?? 'light',
                                            'admin'    => (bool)$usuario->admin,
                                            'url'      => route('usuarios.update', $usuario),
                                        ]) }}"
                                        title="{{ __('Editar') }}">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                {{-- Eliminar --}}
                                @if($usuario->id !== auth()->id())
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-usuario"
                                            data-user-name="{{ $usuario->name }}"
                                            data-url="{{ route('usuarios.destroy', $usuario) }}"
                                            title="{{ __('Eliminar') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            {{ __('No hay usuarios registrados.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($usuarios->hasPages())
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <small class="text-muted">
            {{ __('Mostrando') }} {{ $usuarios->firstItem() }}–{{ $usuarios->lastItem() }} {{ __('de') }} {{ $usuarios->total() }}
        </small>
        {{ $usuarios->links() }}
    </div>
@endif


{{-- ── Modal CREAR ─────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-crear" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Nuevo usuario') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('usuarios.store') }}">
                @csrf
                <div class="modal-body">
                    @if($openCreate && $errors->any())
                        <div class="alert alert-danger py-2 small">
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre de usuario') }}</label>
                        <input type="text" name="name" class="form-control" required
                               value="{{ old('name') }}" autocomplete="off">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('Contraseña') }}</label>
                            <input type="password" name="password" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('Confirmar contraseña') }}</label>
                            <input type="password" name="password_confirmation" class="form-control" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('Idioma') }}</label>
                            <select name="language" class="form-select">
                                <option value="es" {{ old('language','es') === 'es' ? 'selected' : '' }}>{{ __('Español') }}</option>
                                <option value="en" {{ old('language') === 'en' ? 'selected' : '' }}>{{ __('Inglés') }}</option>
                                <option value="fr" {{ old('language') === 'fr' ? 'selected' : '' }}>{{ __('Francés') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('Tema') }}</label>
                            <select name="theme" class="form-select">
                                <option value="light" {{ old('theme','light') === 'light' ? 'selected' : '' }}>{{ __('Claro') }}</option>
                                <option value="dark"  {{ old('theme') === 'dark' ? 'selected' : '' }}>{{ __('Oscuro') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Zona horaria') }}</label>
                        <select name="timezone" class="form-select">
                            @foreach($zonaHoraria as $value => $label)
                                <option value="{{ $value }}" {{ old('timezone','Europe/Madrid') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="admin" id="crear-admin" class="form-check-input" value="1"
                               {{ old('admin') ? 'checked' : '' }}>
                        <label class="form-check-label" for="crear-admin">{{ __('Administrador') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Crear usuario') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- ── Modal EDITAR (único, poblado por JS) ────────────────────────────────── --}}
<div class="modal fade" id="modal-editar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Editar') }}: <span id="modal-editar-nombre"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="form-editar-usuario" action="">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre de usuario') }}</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required autocomplete="off">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('Nueva contraseña') }}</label>
                            <input type="password" name="password" id="edit-password" class="form-control"
                                   placeholder="{{ __('Dejar en blanco para no cambiar') }}" autocomplete="new-password">
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('Confirmar contraseña') }}</label>
                            <input type="password" name="password_confirmation" id="edit-password-confirm" class="form-control">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">{{ __('Idioma') }}</label>
                            <select name="language" id="edit-language" class="form-select">
                                <option value="es">{{ __('Español') }}</option>
                                <option value="en">{{ __('Inglés') }}</option>
                                <option value="fr">{{ __('Francés') }}</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">{{ __('Tema') }}</label>
                            <select name="theme" id="edit-theme" class="form-select">
                                <option value="light">{{ __('Claro') }}</option>
                                <option value="dark">{{ __('Oscuro') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Zona horaria') }}</label>
                        <select name="timezone" id="edit-timezone" class="form-select">
                            @foreach($zonaHoraria as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="admin" id="edit-admin" class="form-check-input" value="1">
                        <label class="form-check-label" for="edit-admin">{{ __('Administrador') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Guardar cambios') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- Modal confirmar eliminar usuario --}}
<div class="modal fade" id="modal-eliminar-usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i>{{ __('Eliminar usuario') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('¿Eliminar el usuario') }} <strong id="modal-usuario-nombre"></strong>?
                {{ __('Esta acción no se puede deshacer.') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <form id="form-eliminar-usuario" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Eliminar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('assets/js/usuarios-index.js') }}"></script>
@if($openCreate)
<script>document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('modal-crear')).show());</script>
@endif
@endpush
