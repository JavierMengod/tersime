@extends('layouts.plantilla')

@section('title', __('Dispositivos'))

@section('contenido')
<div class="container-fluid px-2">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Dispositivos') }}</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-device-create">
            {{ __('Crear dispositivo') }}
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
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('Identificador') }}</th>
                            <th class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispositivos as $device)
                            <tr>
                                <td>{{ $device->nombre }}</td>
                                <td class="text-truncate" style="max-width: 300px;">
                                    {{ $device->influx_tag }}
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-device-edit"
                                            data-id="{{ $device->id }}"
                                            data-nombre="{{ $device->nombre }}"
                                            data-influx-tag="{{ $device->influx_tag }}">
                                        {{ __('Editar') }}
                                    </button>
                                    <button class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-device-delete"
                                            data-id="{{ $device->id }}"
                                            data-nombre="{{ $device->nombre }}">
                                        {{ __('Eliminar') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">
                                    {{ __('No hay dispositivos dados de alta.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($dispositivos->hasPages())
        <div class="mt-3">
            {{ $dispositivos->links() }}
        </div>
    @endif

</div>

{{-- ── Modal Crear ──────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-device-create" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Crear dispositivo') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('dispositivo.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Nombre') }}</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Identificador del sensor') }}</label>
                        <select name="influx_tag" class="form-select" required>
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
                        <select name="influx_tag" id="edit-influx-tag" class="form-select" required>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('modal-device-edit').addEventListener('show.bs.modal', e => {
        const btn = e.relatedTarget;
        document.getElementById('form-device-edit').action = `/dispositivos/${btn.dataset.id}`;
        document.getElementById('edit-nombre').value        = btn.dataset.nombre;
        document.getElementById('edit-influx-tag').value   = btn.dataset.influxTag;
    });

    document.getElementById('modal-device-delete').addEventListener('show.bs.modal', e => {
        const btn = e.relatedTarget;
        document.getElementById('form-device-delete').action  = `/dispositivos/${btn.dataset.id}`;
        document.getElementById('delete-nombre').textContent  = btn.dataset.nombre;
    });
});
</script>
@endpush

@endsection
