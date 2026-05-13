@extends('layouts.plantilla')

@section('title', __('Registro de Informes'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Registro de Informes') }}</h2>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Archivo') }}</th>
                    <th>{{ __('Dispositivos') }}</th>
                    <th>{{ __('Periodo') }}</th>
                    <th>{{ __('Canales') }}</th>
                    <th>{{ __('Tamaño') }}</th>
                    <th>{{ __('Generado') }}</th>
                    <th>{{ __('Acciones') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($registros as $registro)
                    <tr>
                        <td>{{ $registro->nombre_archivo ?? '-' }}</td>

                        <td>
                            @php
                                $dispositivos = $registro->dispositivos ?? collect();
                            @endphp

                            @if($dispositivos->count() > 0)
                                {{ $dispositivos->pluck('nombre')->implode(', ') }}
                            @else
                                <em>-</em>
                            @endif
                        </td>

                        <td>
                            @if($registro->periodo_from && $registro->periodo_to)
                                {{ \Carbon\Carbon::parse($registro->periodo_from)->format('Y-m-d') }} →
                                {{ \Carbon\Carbon::parse($registro->periodo_to)->format('Y-m-d') }}
                            @else
                                <em>{{ __('No definido') }}</em>
                            @endif
                        </td>

                        <td>
                            @php
                                $canales = [];
                                if ($registro->correo) $canales[] = 'Correo';
                                if ($registro->discord) $canales[] = 'Discord';
                                if ($registro->telegram) $canales[] = 'Telegram';
                            @endphp

                            @if(count($canales) > 0)
                                {{ implode(' / ', $canales) }}
                            @else
                                <em>-</em>
                            @endif
                        </td>

                        <td>
                            @if($registro->size_bytes)
                                {{ number_format($registro->size_bytes / 1024, 2) }} KB
                            @else
                                <em>-</em>
                            @endif
                        </td>

                        <td>
                            @if($registro->generated_at)
                                {{ \Carbon\Carbon::parse($registro->generated_at)->format('Y-m-d H:i') }}
                            @else
                                <em>-</em>
                            @endif
                        </td>

                        <td>
                            <div class="d-flex gap-2">
                                <a href="{{ route('informes.registros.download', $registro) }}"
                                   class="btn btn-sm btn-success"
                                   title="{{ __('Descargar') }}">
                                    <i class="bi bi-download"></i> {{ __('Descargar') }}
                                </a>

                                <form method="POST"
                                      action="{{ route('informes.registros.destroy', $registro) }}"
                                      onsubmit="return confirm('{{ __('¿Eliminar este informe?') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="{{ __('Eliminar') }}">
                                        <i class="bi bi-trash"></i> {{ __('Eliminar') }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted">
                            {{ __('No hay informes registrados aún') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
