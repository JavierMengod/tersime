@extends('layouts.plantilla')

@section('title', __('Notificaciones'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Notificaciones') }}</h2>
        @if ($noLeidas > 0)
            <span class="badge bg-danger mt-1">{{ $noLeidas }} {{ __('sin leer') }}</span>
        @endif
    </div>
    @if ($noLeidas > 0)
        <form method="POST" action="{{ route('notificaciones.read_all') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-check2-all"></i> {{ __('Marcar todas leídas') }}
            </button>
        </form>
    @endif
</div>

{{-- Tabs de filtro --}}
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ $tipo === 'todas' ? 'active' : '' }}"
           href="{{ route('notificaciones.index', ['tipo' => 'todas']) }}">
            {{ __('Todas') }}
            <span class="badge bg-secondary ms-1">{{ $alertas->count() + $informes->count() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tipo === 'alertas' ? 'active' : '' }}"
           href="{{ route('notificaciones.index', ['tipo' => 'alertas']) }}">
            <i class="bi bi-exclamation-triangle text-warning"></i> {{ __('Alertas') }}
            <span class="badge bg-warning text-dark ms-1">{{ $alertas->count() }}</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tipo === 'informes' ? 'active' : '' }}"
           href="{{ route('notificaciones.index', ['tipo' => 'informes']) }}">
            <i class="bi bi-file-earmark-pdf text-primary"></i> {{ __('Informes') }}
            <span class="badge bg-primary ms-1">{{ $informes->count() }}</span>
        </a>
    </li>
</ul>

@if ($feed->isEmpty())
    <div class="text-center text-muted py-5">
        <i class="bi bi-bell-slash fs-1 d-block mb-3"></i>
        <p>{{ __('No hay notificaciones en esta categoría.') }}</p>
    </div>
@else
    <div class="list-group shadow-sm">
        @foreach ($feed as $item)
            <div class="list-group-item list-group-item-action d-flex gap-3 py-3">
                {{-- Icono --}}
                <div class="d-flex align-items-start pt-1">
                    <i class="{{ $item['iconClass'] }} fs-5"></i>
                </div>

                {{-- Contenido --}}
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <span class="fw-semibold">{{ $item['titulo'] }}</span>
                            <span class="badge {{ $item['badgeClass'] }} ms-2 small">{{ $item['badgeText'] }}</span>
                        </div>
                        <small class="text-muted ms-3 text-nowrap">
                            {{ $item['fecha'] ? $item['fecha']->diffForHumans() : '' }}
                        </small>
                    </div>

                    <p class="mb-1 mt-1 text-muted small">{{ $item['mensaje'] }}</p>

                    @if (!empty($item['meta']))
                        <small class="text-secondary">
                            <i class="bi bi-hdd"></i> {{ $item['meta'] }}
                        </small>
                    @endif

                    {{-- Canales de alerta --}}
                    @if ($item['tipo'] === 'alerta' && !empty($item['canales']))
                        <div class="mt-1">
                            @foreach ($item['canales'] as $canal)
                                @if ($canal === 'telegram')
                                    <span class="badge bg-info text-dark"><i class="bi bi-telegram"></i> Telegram</span>
                                @elseif ($canal === 'correo' || $canal === 'email')
                                    <span class="badge bg-warning text-dark"><i class="bi bi-envelope"></i> Email</span>
                                @elseif ($canal === 'discord')
                                    <span class="badge bg-secondary"><i class="bi bi-discord"></i> Discord</span>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Acción descargar informe --}}
                    @if ($item['tipo'] === 'informe')
                        <div class="mt-2">
                            <a href="{{ route('informes.download', $item['objeto']) }}"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-download"></i> {{ __('Descargar PDF') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($feed->hasPages())
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <small class="text-muted">
                {{ __('Mostrando') }} {{ $feed->firstItem() }}–{{ $feed->lastItem() }} {{ __('de') }} {{ $feed->total() }}
            </small>
            {{ $feed->links() }}
        </div>
    @endif
@endif

@endsection
