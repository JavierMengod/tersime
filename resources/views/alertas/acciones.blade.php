@extends('layouts.plantilla')

@section('title', __('Reglas de Monitorización'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Reglas de alerta') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Notificaciones automáticas cuando se cumple una condición') }}</p>
    </div>
    <button class="btn btn-primary d-flex align-items-center gap-2"
            data-bs-toggle="modal"
            data-bs-target="#modal-rule-create">
        <i class="fas fa-plus"></i>
        <span class="d-none d-sm-inline">{{ __('Nueva regla') }}</span>
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@forelse($reglas as $regla)
@php
    $state = $regla->alert_state;
    $borderColor = $state === 'firing' ? '#dc3545'
        : ($state === 'pending' ? '#ffc107'
        : ($regla->is_active ? '#198754' : '#adb5bd'));
@endphp
<div class="card border-0 shadow-sm mb-3" style="border-left: 4px solid {{ $borderColor }} !important;">
    <div class="card-body">
        <div class="row align-items-center g-3">

            {{-- Nombre + estado --}}
            <div class="col-12 col-md-3">
                <div class="d-flex align-items-start gap-2">
                    <div class="mt-1">
                        @if($state === 'firing')
                            <span class="text-danger"><i class="fas fa-bell fa-lg"></i></span>
                        @elseif($state === 'pending')
                            <span class="text-warning"><i class="fas fa-clock fa-lg"></i></span>
                        @elseif($regla->is_active)
                            <span class="text-success"><i class="fas fa-shield-alt fa-lg"></i></span>
                        @else
                            <span class="text-muted"><i class="fas fa-shield-alt fa-lg"></i></span>
                        @endif
                    </div>
                    <div>
                        <div class="fw-semibold lh-sm">{{ $regla->name }}</div>
                        <div class="mt-1 d-flex flex-wrap gap-1">
                            @if($regla->is_active)
                                <span class="badge rounded-pill" style="background:#d1fae5;color:#065f46;font-size:.7rem;">{{ __('Activa') }}</span>
                            @else
                                <span class="badge rounded-pill" style="background:#f3f4f6;color:#6b7280;font-size:.7rem;">{{ __('Inactiva') }}</span>
                            @endif
                            @if($state === 'firing')
                                <span class="badge rounded-pill bg-danger" style="font-size:.7rem;">🔥 {{ __('Disparada') }}</span>
                            @elseif($state === 'pending')
                                <span class="badge rounded-pill bg-warning text-dark" style="font-size:.7rem;">⏳ {{ __('Pendiente') }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Condición en lenguaje natural --}}
            <div class="col-12 col-md-4">
                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:.65rem;letter-spacing:.05em;">{{ __('Condición') }}</div>
                <div class="d-flex align-items-center flex-wrap gap-1">
                    <span class="badge bg-light text-dark border" style="font-size:.8rem;">
                        <i class="fas fa-bolt me-1 text-warning"></i>valor
                    </span>
                    <span class="fw-semibold text-primary" style="font-size:.85rem;">{{ $regla->operator_label }}</span>
                    <span class="badge bg-light text-dark border" style="font-size:.8rem;">
                        {{ $regla->comparison_value }} kWh
                    </span>
                    @if($regla->for_duration > 0)
                        <span class="text-muted small">{{ __('durante') }}</span>
                        <span class="badge bg-light text-dark border" style="font-size:.8rem;">
                            <i class="fas fa-clock me-1"></i>{{ $regla->for_duration }} min
                        </span>
                    @endif
                </div>
            </div>

            {{-- Dispositivos --}}
            <div class="col-12 col-md-3">
                <div class="small text-muted mb-1 text-uppercase fw-semibold" style="font-size:.65rem;letter-spacing:.05em;">{{ __('Dispositivos') }}</div>
                <div class="d-flex flex-wrap gap-1">
                    @forelse($regla->dispositivos as $d)
                        <span class="badge rounded-pill bg-light text-dark border" style="font-size:.75rem;">
                            <i class="fas fa-microchip me-1 text-muted"></i>{{ $d->nombre }}
                        </span>
                    @empty
                        <span class="text-muted small">—</span>
                    @endforelse
                </div>
            </div>

            {{-- Canales + acciones --}}
            <div class="col-12 col-md-2 d-flex align-items-center justify-content-between justify-content-md-end gap-2 flex-wrap">
                <div class="d-flex gap-2 me-1">
                    @foreach($regla->channel_badges as $ch)
                        <span title="{{ $ch['label'] }}" class="{{ $ch['color'] }} fs-5"><i class="{{ $ch['icon'] }}"></i></span>
                    @endforeach
                    @if(empty($regla->channel_badges))
                        <span class="text-muted small">—</span>
                    @endif
                </div>
                {{-- Toggle activo/inactivo --}}
                <form action="{{ route('alertas.reglas.toggle', $regla->id) }}" method="POST" class="d-inline">
                    @csrf @method('PATCH')
                    <button type="submit"
                            class="btn btn-sm {{ $regla->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                            title="{{ $regla->is_active ? __('Desactivar') : __('Activar') }}">
                        <i class="fas {{ $regla->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                        <span class="d-none d-lg-inline ms-1">{{ $regla->is_active ? __('Desactivar') : __('Activar') }}</span>
                    </button>
                </form>
                {{-- Editar --}}
                <button class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#modal-rule-edit"
                        data-rule="{{ json_encode([
                            'id'             => $regla->id,
                            'name'           => $regla->name,
                            'devices'        => $regla->dispositivos->pluck('id')->toArray(),
                            'operator'       => $regla->operator,
                            'value'          => $regla->comparison_value,
                            'for_duration'   => $regla->for_duration,
                            'methods'        => [
                                'telegram' => $regla->telegram_enabled,
                                'email'    => $regla->email_enabled,
                                'discord'  => $regla->discord_enabled,
                            ],
                            'templates' => [
                                'telegram' => $regla->template_telegram,
                                'email'    => $regla->template_email,
                                'discord'  => $regla->template_discord,
                            ],
                            'recipient_email' => $regla->recipient_email,
                            'update_url'      => route('alertas.reglas.update', $regla->id),
                            'delete_url'      => route('alertas.reglas.destroy', $regla->id),
                        ]) }}"
                        title="{{ __('Editar') }}">
                    <i class="fas fa-pencil-alt"></i>
                    <span class="d-none d-lg-inline ms-1">{{ __('Editar') }}</span>
                </button>
            </div>

        </div>
    </div>
</div>

@empty

<div class="text-center py-5">
    <div class="mb-3" style="font-size:3rem;opacity:.25;"><i class="fas fa-bell-slash"></i></div>
    <h5 class="text-muted">{{ __('Sin reglas configuradas') }}</h5>
    <p class="text-muted small mb-4">{{ __('Crea tu primera regla para recibir notificaciones automáticas cuando un dispositivo supere un umbral.') }}</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-rule-create">
        <i class="fas fa-plus me-2"></i>{{ __('Crear primera regla') }}
    </button>
</div>

@endforelse

@if($reglas->hasPages())
    <div class="mt-3">
        {{ $reglas->links() }}
    </div>
@endif

{{-- Modales: crear, editar y borrar --}}
@include('alertas.partials.modals-regla')

@push('scripts')
<script src="{{ asset('assets/js/alertas-acciones.js') }}"></script>
@endpush

@endsection
