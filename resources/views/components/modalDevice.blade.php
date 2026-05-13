@props([
    'id', // string: identificador del modal
    'isEdit', // bool: true = edición, false = creación
    'device', // modelo Device o null
    'urlList', // array de URLs disponibles para asignar
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    {{ $isEdit ? __('Editar dispositivo') : __('Crear dispositivo') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form
                action="{{ $isEdit ? route('dispositivo.update', $device) : route('dispositivo.store') }}"
                method="POST">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="modal-body">

                    {{-- Nombre --}}
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre') }}</label>
                        <input type="text" name="nombre" class="form-control"
                            value="{{ old('nombre', $device->nombre ?? '') }}" required>
                    </div>

                    @if ($isEdit)
                        <input type="hidden" name="nombre_original" value="{{ $device->nombre }}">
                    @endif
                    {{-- URL Grafana --}}
                    <div class="mb-3">
                        <label class="form-label">{{ __('URL Grafana') }}</label>
                        <select name="URL" class="form-select" required>
                            <option value="" disabled {{ $device ? '' : 'selected' }}>
                                {{ __('-- Selecciona una URL --') }}
                            </option>
                            @foreach ($urlList as $url)
                                <option value="{{ $url }}"
                                    {{ old('URL', $device->URL ?? '') === $url ? 'selected' : '' }}>
                                    {{ $url }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cerrar') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ $isEdit ? __('Guardar cambios') : __('Crear dispositivo') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
