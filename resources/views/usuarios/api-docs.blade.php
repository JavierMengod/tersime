@extends('layouts.plantilla')

@section('title', __('Referencia de la API'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Referencia de la API') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Documentación de los endpoints disponibles') }}</p>
    </div>
    <a href="{{ route('usuarios.tokens.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>{{ __('Volver a Tokens') }}
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="apiDocs">

            {{-- Auth --}}
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button py-2" type="button"
                            data-bs-toggle="collapse" data-bs-target="#doc-auth">
                        <span class="badge bg-success me-2">POST</span>
                        <span class="badge bg-secondary me-2">GET</span>
                        <strong>{{ __('Autenticación') }}</strong>
                    </button>
                </h2>
                <div id="doc-auth" class="accordion-collapse collapse show" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/auth/login</code></td>
                                    <td class="text-muted">{{ __('Obtener token. Body') }}: <code>{"name":"…","password":"…"}</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/auth/logout</code> <span class="badge bg-warning text-dark">🔒</span></td>
                                    <td class="text-muted">{{ __('Revocar token actual') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/auth/me</code> <span class="badge bg-warning text-dark">🔒</span></td>
                                    <td class="text-muted">{{ __('Datos del usuario autenticado') }}</td>
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
                        <strong>{{ __('Dispositivos') }}</strong>
                    </button>
                </h2>
                <div id="doc-devices" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices</code></td>
                                    <td class="text-muted">{{ __('Lista de dispositivos habilitados') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/current</code></td>
                                    <td class="text-muted">{{ __('Último valor kWh (últimas 24 h)') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/consumption</code></td>
                                    <td class="text-muted">{{ __('Consumo. Params') }}: <code>from</code>, <code>to</code> (YYYY-MM-DD)</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/stats</code></td>
                                    <td class="text-muted">{{ __('Estadísticas (media, máx, mín, σ, factor de carga). Params') }}: <code>from</code>, <code>to</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/devices/{id}/forecast</code></td>
                                    <td class="text-muted">{{ __('Predicción Prophet. Param') }}: <code>hours</code> (1–168, {{ __('def') }}. 24)</td>
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
                        <strong>{{ __('Reglas de alerta') }}</strong>
                    </button>
                </h2>
                <div id="doc-rules" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/rules</code></td>
                                    <td class="text-muted">{{ __('Listar reglas del usuario') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-success">POST</span></td>
                                    <td><code>/api/rules</code></td>
                                    <td class="text-muted">{{ __('Crear regla') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">PUT</span></td>
                                    <td><code>/api/rules/{id}</code></td>
                                    <td class="text-muted">{{ __('Actualizar regla') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">DEL</span></td>
                                    <td><code>/api/rules/{id}</code></td>
                                    <td class="text-muted">{{ __('Eliminar regla') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">PATCH</span></td>
                                    <td><code>/api/rules/{id}/toggle</code></td>
                                    <td class="text-muted">{{ __('Activar / desactivar regla') }}</td>
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
                        <strong>{{ __('Historial de alertas') }}</strong>
                    </button>
                </h2>
                <div id="doc-alerts" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/alerts</code></td>
                                    <td class="text-muted">{{ __('Params') }}: <code>device</code>, <code>rule</code>, <code>type</code>, <code>from</code>, <code>to</code>, <code>per_page</code></td>
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
                        <strong>{{ __('Informes') }}</strong>
                    </button>
                </h2>
                <div id="doc-reports" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/reports</code></td>
                                    <td class="text-muted">{{ __('Listar informes. Param') }}: <code>per_page</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/reports/{id}/download</code></td>
                                    <td class="text-muted">{{ __('Descargar PDF') }}</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-danger">DEL</span></td>
                                    <td><code>/api/reports/{id}</code></td>
                                    <td class="text-muted">{{ __('Eliminar informe') }}</td>
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
                        <strong>{{ __('Consumo agregado') }}</strong>
                    </button>
                </h2>
                <div id="doc-consumption" class="accordion-collapse collapse" data-bs-parent="#apiDocs">
                    <div class="accordion-body p-0">
                        <table class="table table-sm mb-0" style="font-size:.82rem;">
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/consumption/summary</code></td>
                                    <td class="text-muted">{{ __('Total kWh por dispositivo. Params') }}: <code>from</code>, <code>to</code>, <code>devices[]</code></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">GET</span></td>
                                    <td><code>/api/consumption/cost</code></td>
                                    <td class="text-muted">{{ __('Coste estimado. Params') }}: <code>from</code>, <code>to</code>, <code>devices[]</code>, <code>rate</code> (€/kWh)</td>
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

@endsection
