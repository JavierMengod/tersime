@extends('layouts.plantilla')

@section('title', __('Reglas de Monitorización'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Reglas de Monitorización') }}</h2>
        <button class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modal-rule-create">
            {{ __('Crear regla') }}
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Nombre') }}</th>
                    <th>{{ __('Dispositivos') }}</th>
                    <th>{{ __('Condición') }}</th>
                    <th>{{ __('Canales') }}</th>
                    <th>{{ __('Estado') }}</th>
                    <th>{{ __('Acción') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach(auth()->user()->rules as $regla)
                <tr>
                    <td>{{ $regla->name }}</td>
                    <td>{{ $regla->dispositivos->pluck('nombre')->join(', ') ?: '—' }}</td>
                    <td><code>consumo {{ $regla->operator }} {{ $regla->comparison_value }} W</code></td>
                    <td>
                        @if($regla->telegram_enabled)<span class="badge bg-info text-dark">Telegram</span>@endif
                        @if($regla->email_enabled)<span class="badge bg-warning text-dark">{{ __('Correo') }}</span>@endif
                        @if($regla->discord_enabled)<span class="badge bg-secondary text-light">Discord</span>@endif
                    </td>
                    <td>
                        @if($regla->is_active)<span class="badge bg-success">{{ __('Activo') }}</span>
                        @else<span class="badge bg-danger">{{ __('Inactivo') }}</span>@endif
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#modal-rule-{{ $regla->id }}">
                            {{ __('Editar') }}
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

{{-- Modal de creación --}}
<x-modalAlerta
    :is-edit="false"
    :devices-list="$dispositivos" />

{{-- Modal de edición, uno por cada regla --}}
@foreach(auth()->user()->rules as $regla)
    <x-modalAlerta
        :is-edit="true"
        :rule="[
            'id'        => $regla->id,
            'name'      => $regla->name,
            'devices'   => $regla->dispositivo
                              ? [$regla->dispositivo->id]
                              : [],
            'operator'  => $regla->operator,
            'value'     => $regla->comparison_value,
            'duration'  => $regla->time_range,
            'methods'   => [
                'telegram' => $regla->telegram_enabled,
                'email'    => $regla->email_enabled,
                'discord'  => $regla->discord_enabled,
            ],
            'templates' => [
                'telegram' => $regla->template_telegram,
                'email'    => $regla->template_email,
                'discord'  => $regla->template_discord,
            ],
            'active'    => $regla->is_active,
            'recipient_email' => $regla->recipient_email,
        ]"
        :devices-list="$dispositivos" />
@endforeach
@endsection
