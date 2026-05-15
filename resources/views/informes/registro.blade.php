@extends('layouts.plantilla')

@section('title', __('Registro de Informes'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">{{ __('Registro de Informes') }}</h2>
            <p class="text-muted mb-0 small">{{ __('Historial de PDFs generados') }}</p>
        </div>
        @if($registros->total() > 0)
            <span class="badge bg-secondary fs-6">{{ $registros->total() }} {{ __('informes') }}</span>
        @endif
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" style="font-size:.82rem;">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Archivo') }}</th>
                            <th>{{ __('Tipo') }}</th>
                            <th>{{ __('Dispositivos') }}</th>
                            <th>{{ __('Período') }}</th>
                            <th class="d-none d-lg-table-cell">{{ __('Canales') }}</th>
                            <th class="d-none d-lg-table-cell">{{ __('Tamaño') }}</th>
                            <th>{{ __('Generado') }}</th>
                            <th>{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registros as $registro)
                            <tr>
                                <td style="max-width:160px;">
                                    <span class="fw-semibold text-truncate d-block" title="{{ $registro->nombre_archivo ?? '' }}">
                                        {{ $registro->nombre_archivo ?? '-' }}
                                    </span>
                                </td>

                                <td class="text-nowrap">
                                    @if($registro->tipo === 'Programado')
                                        <span class="badge bg-info text-dark">{{ __('Prog.') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Dem.') }}</span>
                                    @endif
                                </td>

                                <td style="max-width:130px;">
                                    @php $dispositivos = $registro->dispositivos; @endphp
                                    @if($dispositivos && $dispositivos->count() > 0)
                                        <span class="text-truncate d-block" title="{{ $dispositivos->pluck('nombre')->implode(', ') }}">
                                            {{ $dispositivos->pluck('nombre')->implode(', ') }}
                                        </span>
                                    @else
                                        <em class="text-muted">-</em>
                                    @endif
                                </td>

                                <td class="text-nowrap">
                                    @if($registro->periodo_from && $registro->periodo_to)
                                        {{ \Carbon\Carbon::parse($registro->periodo_from)->format('d/m/y') }}
                                        <span class="text-muted">→</span>
                                        {{ \Carbon\Carbon::parse($registro->periodo_to)->format('d/m/y') }}
                                    @else
                                        <em class="text-muted">-</em>
                                    @endif
                                </td>

                                <td class="d-none d-lg-table-cell">
                                    @if($registro->telegram)
                                        <i class="fab fa-telegram text-info" title="Telegram"></i>
                                    @endif
                                    @if($registro->correo)
                                        <i class="fas fa-envelope text-warning ms-1" title="{{ __('Correo') }}"></i>
                                    @endif
                                    @if($registro->discord)
                                        <i class="fab fa-discord text-secondary ms-1" title="Discord"></i>
                                    @endif
                                    @if(!$registro->telegram && !$registro->correo && !$registro->discord)
                                        <em class="text-muted">-</em>
                                    @endif
                                </td>

                                <td class="d-none d-lg-table-cell text-nowrap">
                                    @if($registro->size_bytes)
                                        {{ number_format($registro->size_bytes / 1024, 0) }} KB
                                    @else
                                        <em class="text-muted">-</em>
                                    @endif
                                </td>

                                <td class="text-nowrap">
                                    @if($registro->generated_at)
                                        <div>{{ $registro->generated_at->format('d/m/y') }}</div>
                                        <div class="text-muted" style="font-size:.75rem;">{{ $registro->generated_at->format('H:i') }}</div>
                                    @else
                                        <em class="text-muted">-</em>
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('informes.download', $registro) }}"
                                           class="btn btn-sm btn-success py-0 px-2"
                                           title="{{ __('Descargar') }}">
                                            <i class="fas fa-download"></i>
                                        </a>

                                        <form method="POST"
                                              action="{{ route('informes.destroy', $registro) }}"
                                              onsubmit="return confirm('{{ __('¿Eliminar este informe?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('Eliminar') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="mb-2" style="font-size:2.5rem;opacity:.2;"><i class="fas fa-file-pdf"></i></div>
                                    <div class="text-muted">{{ __('No hay informes registrados aún') }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($registros->hasPages())
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">
                {{ __('Mostrando') }} {{ $registros->firstItem() }}–{{ $registros->lastItem() }} {{ __('de') }} {{ $registros->total() }}
            </small>
            {{ $registros->links() }}
        </div>
    @endif
@endsection
