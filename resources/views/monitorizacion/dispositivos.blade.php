@extends('layouts.plantilla')

@section('title', __('Dispositivos'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Dispositivos') }}</h2>
        <button class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modal-device-create">
            {{ __('Crear dispositivo') }}
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('URL') }}</th>
                            <th class="text-end">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispositivos as $device)
                            <tr>
                                <td>{{ $device->nombre }}</td>
                                <td class="text-truncate" style="max-width: 300px;">
                                    <a href="{{ $device->URL }}" target="_blank">
                                        {{ $device->URL }}
                                    </a>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-device-{{ $device->id }}">
                                        {{ __('Editar') }}
                                    </button>
                                    <form action="{{ route('dispositivo.destroy', $device) }}"
                                          method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-danger">
                                            {{ __('Eliminar') }}
                                        </button>
                                    </form>
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

{{-- Modal Crear Dispositivo --}}
<x-modalDevice
    id="modal-device-create"
    :is-edit="false"
    :device="null"
    :url-list="$dispositivosGrafana" />

{{-- Modal Editar Dispositivo (uno por cada) --}}
@foreach($dispositivos as $device)
    <x-modalDevice
        id="modal-device-{{ $device->id }}"
        :is-edit="true"
        :device="$device"
        :url-list="$dispositivosGrafana" />
@endforeach
@endsection
