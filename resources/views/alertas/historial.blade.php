@extends('layouts.plantilla')

@section('title', __('Historial de alertas'))

@php
function sortUrl(string $column, string $currentSort, string $currentDir): string {
    $dir = ($currentSort === $column && $currentDir === 'desc') ? 'asc' : 'desc';
    return request()->fullUrlWithQuery(['sort' => $column, 'dir' => $dir, 'page' => 1]);
}
function sortIcon(string $column, string $currentSort, string $currentDir): string {
    if ($currentSort !== $column) return 'fa-sort text-muted';
    return $currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
}
@endphp

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Historial de alertas') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Registro de todos los disparos y resoluciones') }}</p>
    </div>
    @if($logs->total() > 0)
        <span class="badge bg-secondary fs-6">{{ $logs->total() }} {{ __('registros') }}</span>
    @endif
</div>

{{-- ── FILTROS ──────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('alertas-historial') }}" class="row g-2 align-items-end">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="dir"  value="{{ $dir }}">

            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.65rem;letter-spacing:.05em;">
                    <i class="fas fa-microchip me-1"></i>{{ __('Dispositivo') }}
                </label>
                <select name="device" class="form-select form-select-sm">
                    <option value="">{{ __('Todos los dispositivos') }}</option>
                    @foreach($devices as $d)
                        <option value="{{ $d }}" {{ request('device') === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.65rem;letter-spacing:.05em;">
                    <i class="fas fa-shield-alt me-1"></i>{{ __('Regla') }}
                </label>
                <select name="rule" class="form-select form-select-sm">
                    <option value="">{{ __('Todas las reglas') }}</option>
                    @foreach($rules as $r)
                        <option value="{{ $r }}" {{ request('rule') === $r ? 'selected' : '' }}>{{ $r }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-sm-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.65rem;letter-spacing:.05em;">
                    <i class="fas fa-tag me-1"></i>{{ __('Tipo') }}
                </label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">{{ __('Todos') }}</option>
                    <option value="firing"     {{ request('type') === 'firing'     ? 'selected' : '' }}>🔥 {{ __('Disparada') }}</option>
                    <option value="resolution" {{ request('type') === 'resolution' ? 'selected' : '' }}>✅ {{ __('Resuelta') }}</option>
                </select>
            </div>

            <div class="col-6 col-sm-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.65rem;letter-spacing:.05em;">
                    <i class="fas fa-calendar me-1"></i>{{ __('Desde') }}
                </label>
                <input type="date" name="from" class="form-control form-control-sm"
                       value="{{ request('from') }}">
            </div>

            <div class="col-6 col-sm-4 col-lg-2">
                <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size:.65rem;letter-spacing:.05em;">
                    <i class="fas fa-calendar me-1"></i>{{ __('Hasta') }}
                </label>
                <input type="date" name="to" class="form-control form-control-sm"
                       value="{{ request('to') }}">
            </div>

            <div class="col-6 col-sm-12 col-lg-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-filter me-1"></i>{{ __('Filtrar') }}
                </button>
                @if(request()->hasAny(['device','rule','type','from','to']))
                    <a href="{{ route('alertas-historial', ['sort' => $sort, 'dir' => $dir]) }}"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- ── TABLA ────────────────────────────────────────────────────────────────── --}}
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width:140px;">
                            <a href="{{ sortUrl('created_at', $sort, $dir) }}"
                               class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                {{ __('Fecha') }}
                                <i class="fas {{ sortIcon('created_at', $sort, $dir) }} small"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ sortUrl('type', $sort, $dir) }}"
                               class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                {{ __('Tipo') }}
                                <i class="fas {{ sortIcon('type', $sort, $dir) }} small"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ sortUrl('rule_name', $sort, $dir) }}"
                               class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                {{ __('Regla') }}
                                <i class="fas {{ sortIcon('rule_name', $sort, $dir) }} small"></i>
                            </a>
                        </th>
                        <th>
                            <a href="{{ sortUrl('device_name', $sort, $dir) }}"
                               class="text-decoration-none text-dark d-flex align-items-center gap-1">
                                {{ __('Dispositivo') }}
                                <i class="fas {{ sortIcon('device_name', $sort, $dir) }} small"></i>
                            </a>
                        </th>
                        <th class="d-none d-md-table-cell">{{ __('Canales') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Mensaje') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        {{-- Fecha --}}
                        <td class="text-nowrap">
                            <div class="fw-semibold small">{{ $log->created_at->format('d/m/Y') }}</div>
                            <div class="text-muted" style="font-size:.75rem;">{{ $log->created_at->format('H:i:s') }}</div>
                        </td>

                        {{-- Tipo --}}
                        <td>
                            @if($log->type === 'firing')
                                <span class="badge rounded-pill bg-danger">🔥 {{ __('Disparada') }}</span>
                            @else
                                <span class="badge rounded-pill bg-success">✅ {{ __('Resuelta') }}</span>
                            @endif
                        </td>

                        {{-- Regla --}}
                        <td>
                            <a href="{{ route('alertas-historial', array_merge(request()->query(), ['rule' => $log->rule_name, 'page' => 1])) }}"
                               class="text-decoration-none fw-semibold"
                               title="{{ __('Filtrar por esta regla') }}">
                                {{ $log->rule_name }}
                            </a>
                            @if(!$log->rule_id)
                                <span class="badge bg-light text-muted border ms-1" style="font-size:.65rem;" title="{{ __('Regla eliminada') }}">{{ __('eliminada') }}</span>
                            @endif
                        </td>

                        {{-- Dispositivo --}}
                        <td>
                            <a href="{{ route('alertas-historial', array_merge(request()->query(), ['device' => $log->device_name, 'page' => 1])) }}"
                               class="text-decoration-none d-flex align-items-center gap-1"
                               title="{{ __('Filtrar por este dispositivo') }}">
                                <i class="fas fa-microchip text-muted small"></i>
                                {{ $log->device_name }}
                            </a>
                        </td>

                        {{-- Canales --}}
                        <td class="d-none d-md-table-cell">
                            @php $chList = $log->channelList(); @endphp
                            @if(in_array('telegram', $chList))
                                <i class="fab fa-telegram text-info fs-5" title="Telegram"></i>
                            @endif
                            @if(in_array('email', $chList))
                                <i class="fas fa-envelope text-warning fs-5 ms-1" title="{{ __('Correo') }}"></i>
                            @endif
                            @if(in_array('discord', $chList))
                                <i class="fab fa-discord text-secondary fs-5 ms-1" title="Discord"></i>
                            @endif
                            @if(empty($chList))
                                <span class="text-muted">—</span>
                            @endif
                        </td>

                        {{-- Mensaje --}}
                        <td class="d-none d-lg-table-cell text-muted small" style="max-width:320px;">
                            <span class="text-truncate d-block" style="max-width:320px;" title="{{ $log->message }}">
                                {{ $log->message }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="mb-2" style="font-size:2.5rem;opacity:.2;"><i class="fas fa-history"></i></div>
                            <div class="text-muted">
                                @if(request()->hasAny(['device','rule','type','from','to']))
                                    {{ __('No hay registros con los filtros aplicados.') }}
                                @else
                                    {{ __('Aún no se ha disparado ninguna alerta.') }}
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($logs->hasPages())
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <small class="text-muted">
            {{ __('Mostrando') }} {{ $logs->firstItem() }}–{{ $logs->lastItem() }} {{ __('de') }} {{ $logs->total() }}
        </small>
        {{ $logs->links() }}
    </div>
@endif

@endsection
