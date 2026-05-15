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
                                    data-url="{{ route('tokens.destroy', $token->id) }}"
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

{{-- Documentación de la API --}}
<div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent fw-semibold">
        <i class="bi bi-code-slash me-1"></i>{{ __('Referencia de la API') }}
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="apiDocs">

            {{-- Auth --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-auth">
                        <span class="badge bg-success me-2">POST</span>
                        <span class="badge bg-secondary me-2">POST</span>
                        <span class="badge bg-light text-dark border me-2">GET</span>
                        <strong>Autenticación</strong>
                    </button>
                </h2>
                <div id="doc-auth" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/auth/login</code></td>
                                    <td class="text-muted">Obtener token. Body: <code>{"name":"…","password":"…"}</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/auth/logout</code> <span class="badge bg-warning text-dark">🔒</span></td>
                                    <td class="text-muted">Revocar token actual</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/auth/me</code> <span class="badge bg-warning text-dark">🔒</span></td>
                                    <td class="text-muted">Datos del usuario autenticado</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Devices --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-devices">
                        <span class="badge bg-secondary me-2">GET ×5</span>
                        <strong>Dispositivos</strong>
                    </button>
                </h2>
                <div id="doc-devices" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices</code></td>
                                    <td class="text-muted">Lista de dispositivos habilitados</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/current</code></td>
                                    <td class="text-muted">Último valor kWh (últimas 24 h)</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/consumption</code></td>
                                    <td class="text-muted">Consumo. Params: <code>from</code>, <code>to</code> (YYYY-MM-DD)</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/stats</code></td>
                                    <td class="text-muted">Estadísticas (media, máx, mín, σ, factor de carga). Params: <code>from</code>, <code>to</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/forecast</code></td>
                                    <td class="text-muted">Predicción Prophet. Param: <code>hours</code> (1–168, def. 24)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Rules --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-rules">
                        <span class="badge bg-secondary me-2">GET</span>
                        <span class="badge bg-success me-2">POST</span>
                        <span class="badge bg-primary me-2">PUT</span>
                        <span class="badge bg-danger me-2">DEL</span>
                        <strong>Reglas de alerta</strong>
                    </button>
                </h2>
                <div id="doc-rules" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/rules</code></td>
                                    <td class="text-muted">Listar reglas del usuario</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/rules</code></td>
                                    <td class="text-muted">Crear regla</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">PUT</span></td>
                                    <td><code>/api/rules/{id}</code></td>
                                    <td class="text-muted">Actualizar regla</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">DEL</span></td>
                                    <td><code>/api/rules/{id}</code></td>
                                    <td class="text-muted">Eliminar regla</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">PATCH</span></td>
                                    <td><code>/api/rules/{id}/toggle</code></td>
                                    <td class="text-muted">Activar / desactivar regla</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Alerts --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-alerts">
                        <span class="badge bg-secondary me-2">GET</span>
                        <strong>Historial de alertas</strong>
                    </button>
                </h2>
                <div id="doc-alerts" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/alerts</code></td>
                                    <td class="text-muted">Params: <code>device</code>, <code>rule</code>, <code>type</code>, <code>from</code>, <code>to</code>, <code>per_page</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Reports --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-reports">
                        <span class="badge bg-secondary me-2">GET</span>
                        <span class="badge bg-danger me-2">DEL</span>
                        <strong>Informes</strong>
                    </button>
                </h2>
                <div id="doc-reports" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/reports</code></td>
                                    <td class="text-muted">Listar informes. Param: <code>per_page</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/reports/{id}/download</code></td>
                                    <td class="text-muted">Descargar PDF</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">DEL</span></td>
                                    <td><code>/api/reports/{id}</code></td>
                                    <td class="text-muted">Eliminar informe</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Consumption --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-consumption">
                        <span class="badge bg-secondary me-2">GET ×2</span>
                        <strong>Consumo agregado</strong>
                    </button>
                </h2>
                <div id="doc-consumption" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/consumption/summary</code></td>
                                    <td class="text-muted">Total kWh por dispositivo. Params: <code>from</code>, <code>to</code>, <code>devices[]</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/consumption/cost</code></td>
                                    <td class="text-muted">Coste estimado. Params: <code>from</code>, <code>to</code>, <code>devices[]</code>, <code>rate</code> (€/kWh)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>{{-- /accordion --}}
    </div>
    <div class="card-footer bg-transparent small text-muted">
        <i class="bi bi-lock me-1"></i>{{ __('Todas las rutas marcadas con') }} 🔒 {{ __('requieren cabecera') }}
        <code>Authorization: Bearer &lt;token&gt;</code>
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
            <form method="POST" action="{{ route('tokens.store') }}">
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
<script>
document.querySelectorAll('.btn-eliminar-token').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('modal-token-nombre').textContent = this.dataset.tokenName;
        document.getElementById('form-eliminar-token').action = this.dataset.url;
        new bootstrap.Modal(document.getElementById('modal-eliminar-token')).show();
    });
});

@if(session('token_creado'))
(function () {
    function copyFallback(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }

    document.getElementById('btnCopiar').addEventListener('click', function () {
        var btn = this;
        var token = document.getElementById('tokenTexto').innerText.trim();

        function onCopied() {
            btn.innerHTML = '<i class="bi bi-clipboard-check"></i> {{ __("Copiado") }}';
            btn.classList.replace('btn-outline-secondary', 'btn-success');
            setTimeout(function () {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> {{ __("Copiar") }}';
                btn.classList.replace('btn-success', 'btn-outline-secondary');
            }, 2000);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(token).then(onCopied).catch(function () {
                copyFallback(token);
                onCopied();
            });
        } else {
            copyFallback(token);
            onCopied();
        }
    });
}());
@endif
</script>
@endpush

@endsection
