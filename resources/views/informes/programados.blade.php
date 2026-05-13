@extends('layouts.plantilla')

@section('title', __('Programación de Informes'))

@section('contenido')
@php
    $devicesList = auth()->user()->dispositivos->map(function($d){
        return [
            'id' => $d->id,
            'name' => $d->name ?? $d->nombre ?? $d->URL ?? 'Dispositivo'
        ];
    })->toArray();

    $formatearFrecuencia = function($horas) {
        if (!$horas || $horas < 1) return '-';

        $meses = floor($horas / 720);
        $horas %= 720;
        $semanas = floor($horas / 168);
        $horas %= 168;
        $dias = floor($horas / 24);
        $horas %= 24;

        $partes = [];
        if ($meses > 0) $partes[] = $meses . ' mes' . ($meses > 1 ? 'es' : '');
        if ($semanas > 0) $partes[] = $semanas . ' semana' . ($semanas > 1 ? 's' : '');
        if ($dias > 0) $partes[] = $dias . ' día' . ($dias > 1 ? 's' : '');
        if ($horas > 0) $partes[] = $horas . ' hora' . ($horas > 1 ? 's' : '');

        return implode(', ', $partes);
    };
@endphp

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">{{ __('Programación de Informes') }}</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-schedule-create">
      {{ __('Nueva programación') }}
    </button>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Nombre') }}</th>
          <th>{{ __('Frecuencia') }}</th>
          <th>{{ __('Dispositivos') }}</th>
          <th>{{ __('Canales') }}</th>
          <th>{{ __('Estado') }}</th>
          <th>{{ __('Acciones') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($informes as $informe)
          <tr>
            <td>{{ $informe->nombre }}</td>
            <td>{{ $formatearFrecuencia($informe->periodicidad) }}</td>
            <td>
              @if($informe->dispositivos && $informe->dispositivos->count() > 0)
                {{ $informe->dispositivos->map(function($d){
                    return $d->name ?? $d->nombre ?? $d->URL ?? 'Dispositivo';
                })->implode(', ') }}
              @else
                <em>-</em>
              @endif
            </td>
            <td>
              @if($informe->telegram)
                <span class="badge bg-info text-dark">Telegram</span>
              @endif
              @if($informe->correo)
                <span class="badge bg-warning text-dark">{{ __('Correo') }}</span>
              @endif
              @if($informe->discord)
                <span class="badge bg-secondary text-light">Discord</span>
              @endif
              @if(!$informe->telegram && !$informe->correo && !$informe->discord)
                <em>-</em>
              @endif
            </td>
            <td>
              @if($informe->activo)
                <span class="badge bg-success">{{ __('Activo') }}</span>
              @else
                <span class="badge bg-danger">{{ __('Inactivo') }}</span>
              @endif
            </td>
            <td>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-schedule-{{ $informe->id }}">
                  <i class="bi bi-pencil"></i> {{ __('Editar') }}
                </button>

                <form method="POST" action="{{ route('programaciones.destroy', $informe) }}"
                      onsubmit="return confirm('{{ __('¿Eliminar esta programación?') }}');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i> {{ __('Eliminar') }}
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted">{{ __('No hay programaciones creadas') }}</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(method_exists($informes, 'links'))
    <div class="mt-3">
      {{ $informes->links() }}
    </div>
  @endif

{{-- Modal crear --}}
<x-modalProgramacion
    :is-edit="false"
    :devices-list="$devicesList" />

{{-- Modales de edición --}}
@foreach($informes as $informe)
    @php
        $schedule = [
            'id' => $informe->id,
            'nombre' => $informe->nombre,
            'periodicidad' => $informe->periodicidad,
            'dispositivos' => $informe->dispositivos ? $informe->dispositivos->pluck('id')->toArray() : [],
            'telegram' => (bool) $informe->telegram,
            'correo' => (bool) $informe->correo,
            'discord' => (bool) $informe->discord,
            'correo_destino' => $informe->correo_destino ?? null,
            'activo' => (bool) $informe->activo,
        ];
    @endphp

    <x-modalProgramacion
        :is-edit="true"
        :schedule="$schedule"
        :devices-list="$devicesList" />
@endforeach
@endsection
