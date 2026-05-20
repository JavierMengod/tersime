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
                            <th class="d-none d-md-table-cell">{{ __('Identificador') }}</th>
                            <th class="text-center">{{ __('Datos') }}</th>
                            <th class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispositivos as $device)
                            <tr>
                                <td class="{{ $device->pivot->habilitado ? '' : 'text-muted' }}">
                                    {{ $device->nombre }}
                                    @if(!$device->pivot->habilitado)
                                        <span class="badge bg-secondary ms-1">{{ __('Deshabilitado') }}</span>
                                    @endif
                                </td>
                                <td class="text-truncate d-none d-md-table-cell {{ $device->pivot->habilitado ? '' : 'text-muted' }}" style="max-width: 260px;">
                                    {{ $device->influx_tag }}
                                </td>
                                <td class="text-center">
                                    @if($device->activo)
                                        <span class="badge bg-success">{{ __('Activo') }}</span>
                                    @else
                                        <span class="badge bg-danger">{{ __('Sin datos') }}</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    {{-- Texto en desktop, icono en móvil --}}
                                    <form action="{{ route('dispositivos.toggle', $device) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-sm {{ $device->pivot->habilitado ? 'btn-outline-secondary' : 'btn-outline-success' }} me-1"
                                                title="{{ $device->pivot->habilitado ? __('Deshabilitar') : __('Habilitar') }}">
                                            <span class="d-none d-md-inline">{{ $device->pivot->habilitado ? __('Deshabilitar') : __('Habilitar') }}</span>
                                            <i class="d-md-none fas {{ $device->pivot->habilitado ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-device-edit"
                                            data-id="{{ $device->id }}"
                                            data-nombre="{{ $device->nombre }}"
                                            data-influx-tag="{{ $device->influx_tag }}"
                                            title="{{ __('Editar') }}">
                                        <span class="d-none d-md-inline">{{ __('Editar') }}</span>
                                        <i class="d-md-none fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-device-delete"
                                            data-id="{{ $device->id }}"
                                            data-nombre="{{ $device->nombre }}"
                                            title="{{ __('Eliminar') }}">
                                        <span class="d-none d-md-inline">{{ __('Eliminar') }}</span>
                                        <i class="d-md-none fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
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

@include('monitorizacion.partials.modals-dispositivo')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('modal-device-edit').addEventListener('show.bs.modal', e => {
        const btn    = e.relatedTarget;
        const select = document.getElementById('edit-influx-tag');
        const tag    = btn.dataset.influxTag;

        // Remove any tag injected for a previous device
        select.querySelectorAll('option[data-injected]').forEach(o => o.remove());

        // Insert current tag if not already in the list (it's filtered out server-side)
        if (!Array.from(select.options).some(o => o.value === tag)) {
            const opt = new Option(tag, tag);
            opt.dataset.injected = '1';
            select.add(opt, select.options[1] ?? undefined);
        }

        document.getElementById('form-device-edit').action = `/dispositivos/${btn.dataset.id}`;
        document.getElementById('edit-nombre').value        = btn.dataset.nombre;
        select.value                                        = tag;
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
