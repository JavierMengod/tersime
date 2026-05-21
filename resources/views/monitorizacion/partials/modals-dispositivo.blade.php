{{-- ── Modal Crear ──────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-device-create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Crear dispositivo') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('dispositivos.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre') }}</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Identificador del sensor') }}</label>
                        <select name="etiqueta_influx" class="form-select" required>
                            <option value="" disabled selected>{{ __('-- Selecciona un sensor --') }}</option>
                            @foreach ($dispositivosGrafana as $tag)
                                <option value="{{ $tag }}">{{ $tag }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Crear dispositivo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Editar ─────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-device-edit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Editar dispositivo') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-device-edit" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre') }}</label>
                        <input type="text" name="nombre" id="edit-nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Identificador del sensor') }}</label>
                        <select name="etiqueta_influx" id="edit-etiqueta-influx" class="form-select" required>
                            <option value="" disabled>{{ __('-- Selecciona un sensor --') }}</option>
                            @foreach ($dispositivosGrafana as $tag)
                                <option value="{{ $tag }}">{{ $tag }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Guardar cambios') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal Eliminar ───────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-device-delete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title">{{ __('Eliminar dispositivo') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">
                    {{ __('¿Eliminar') }} <strong id="delete-nombre"></strong>?
                </p>
                <p class="text-muted small mb-0">{{ __('Esta acción no se puede deshacer.') }}</p>
            </div>
            <form id="form-device-delete" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        {{ __('Cancelar') }}
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        {{ __('Eliminar') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
