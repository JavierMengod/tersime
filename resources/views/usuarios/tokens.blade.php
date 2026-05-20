@extends('layouts.plantilla')

@section('title', __('Tokens de API'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Tokens de API') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Gestiona los tokens de acceso a la API REST') }}</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear-token">
        <i class="bi bi-key me-1"></i>{{ __('Nuevo token') }}
    </button>
</div>

{{-- Token recién creado --}}
@if(session('token_creado'))
<div class="alert alert-success alert-dismissible" role="alert">
    <div class="fw-semibold mb-2"><i class="bi bi-shield-check me-1"></i>{{ __('Token generado correctamente') }}</div>
    <div class="d-flex align-items-center gap-2 mb-2">
        <code id="tokenTexto" class="flex-grow-1 p-2 rounded bg-white border user-select-all"
              style="font-size:.85rem;word-break:break-all;">{{ session('token_creado') }}</code>
        <button class="btn btn-sm btn-outline-secondary flex-shrink-0" id="btnCopiar">
            <i class="bi bi-clipboard"></i> {{ __('Copiar') }}
        </button>
    </div>
    <small class="text-danger fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Cópialo ahora. No se mostrará de nuevo.') }}</small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Tabla de tokens --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Nombre') }}</th>
                        <th>{{ __('Creado') }}</th>
                        <th>{{ __('Último uso') }}</th>
                        <th>{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                    <tr>
                        <td class="fw-semibold">{{ $token->name }}</td>
                        <td class="text-muted text-nowrap">{{ $token->created_at->format('d/m/y H:i') }}</td>
                        <td class="text-muted text-nowrap">
                            {{ $token->last_used_at ? $token->last_used_at->format('d/m/y H:i') : '—' }}
                        </td>
                        <td>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger py-0 px-2 btn-eliminar-token"
                                    data-token-id="{{ $token->id }}"
                                    data-token-name="{{ $token->name }}"
                                    data-url="{{ route('usuarios.tokens.destroy', $token->id) }}"
                                    title="{{ __('Eliminar') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-muted">
                            {{ __('No hay tokens. Crea uno para acceder a la API.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Enlace a la documentación de la API --}}
<div class="card border-0 shadow-sm">
    <div class="card-body d-flex align-items-center justify-content-between py-3 px-4">
        <div>
            <span class="fw-semibold"><i class="bi bi-code-slash me-2 text-muted"></i>{{ __('Referencia de la API') }}</span>
            <p class="text-muted small mb-0">{{ __('Consulta todos los endpoints disponibles y cómo usarlos.') }}</p>
        </div>
        <a href="{{ route('usuarios.tokens.docs') }}" class="btn btn-outline-secondary btn-sm">
            {{ __('Ver documentación') }} <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
</div>
{{-- Modal confirmar eliminar token --}}
<div class="modal fade" id="modal-eliminar-token" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i>{{ __('Eliminar token') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('¿Eliminar el token') }} <strong id="modal-token-nombre"></strong>?
                {{ __('Las aplicaciones que lo usen dejarán de funcionar.') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <form id="form-eliminar-token" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Eliminar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal crear token --}}
<div class="modal fade" id="modal-crear-token" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-1"></i>{{ __('Nuevo token de API') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('usuarios.tokens.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre del token') }}</label>
                        <input type="text" name="nombre" class="form-control" required
                               placeholder="{{ __('Ej: script-backup, grafana, home-assistant') }}"
                               autocomplete="off">
                        <div class="form-text">{{ __('Usa un nombre descriptivo para saber para qué sirve.') }}</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Generar token') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('assets/js/usuarios-tokens.js') }}"></script>
@endpush

@endsection
