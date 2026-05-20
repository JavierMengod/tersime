@extends('layouts.plantilla')

@section('title', __('Registro de Informes'))

@php
  $hayActivos = $registros->contains(fn($r) => in_array($r->status, ['pending','processing']));
@endphp

@section('contenido')
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h2 class="mb-0">{{ __('Registro de Informes') }}</h2>
    <p class="text-muted mb-0 small">{{ __('Historial de PDFs generados') }}</p>
  </div>
  <div class="d-flex align-items-center gap-2">
    @if($registros->total() > 0)
      <span class="badge bg-secondary fs-6">{{ $registros->total() }} {{ __('informes') }}</span>
    @endif
    <a href="{{ route('informes.demanda') }}" class="btn btn-sm btn-primary">
      <i class="fas fa-plus me-1"></i>{{ __('Nuevo') }}
    </a>
  </div>
</div>

@if($hayActivos)
  <div class="alert alert-info d-flex align-items-center gap-2 mb-3 py-2">
    <div class="spinner-border spinner-border-sm text-info flex-shrink-0" role="status"></div>
    <span class="small">{{ __('Hay informes en proceso. Esta página se actualiza automáticamente.') }}</span>
  </div>
@endif

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0" style="font-size:.82rem;">
        <thead class="table-light">
          <tr>
            <th>{{ __('Estado') }}</th>
            <th class="d-none d-md-table-cell">{{ __('Tipo') }}</th>
            <th class="d-none d-md-table-cell">{{ __('Dispositivos') }}</th>
            <th>{{ __('Período') }}</th>
            <th class="d-none d-lg-table-cell">{{ __('Canales') }}</th>
            <th class="d-none d-xl-table-cell">{{ __('Tamaño') }}</th>
            <th>{{ __('Fecha') }}</th>
            <th>{{ __('Acciones') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($registros as $registro)
            <tr class="{{ in_array($registro->status, ['pending','processing']) ? 'table-warning' : ($registro->status === 'failed' ? 'table-danger' : '') }}"
                @if(in_array($registro->status, ['pending','processing'])) data-informe-id="{{ $registro->id }}" data-status-url="{{ route('informes.status', $registro->id) }}" @endif>

              {{-- Estado --}}
              <td class="text-nowrap">
                @if($registro->status === 'completed')
                  <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Listo') }}</span>
                @elseif($registro->status === 'processing')
                  <span class="badge bg-primary">
                    <span class="spinner-border spinner-border-sm me-1" style="width:.65rem;height:.65rem;"></span>{{ __('Generando') }}
                  </span>
                @elseif($registro->status === 'pending')
                  <span class="badge bg-warning text-dark">
                    <span class="spinner-border spinner-border-sm me-1" style="width:.65rem;height:.65rem;"></span>{{ __('En cola') }}
                  </span>
                @elseif($registro->status === 'failed')
                  <span class="badge bg-danger" @if($registro->error_message) title="{{ $registro->error_message }}" data-bs-toggle="tooltip" @endif>
                    <i class="fas fa-times me-1"></i>{{ __('Error') }}
                  </span>
                @else
                  <span class="badge bg-secondary">{{ $registro->status }}</span>
                @endif
              </td>

              {{-- Tipo --}}
              <td class="text-nowrap d-none d-md-table-cell">
                @if($registro->tipo === 'Programado')
                  <span class="badge bg-info text-dark">{{ __('Prog.') }}</span>
                @else
                  <span class="badge bg-secondary">{{ __('Dem.') }}</span>
                @endif
              </td>

              {{-- Dispositivos --}}
              <td style="max-width:130px;" class="d-none d-md-table-cell">
                @php $devs = $registro->dispositivos; @endphp
                @if($devs && $devs->count() > 0)
                  <span class="text-truncate d-block" title="{{ $devs->pluck('nombre')->implode(', ') }}">
                    {{ $devs->pluck('nombre')->implode(', ') }}
                  </span>
                @else
                  <em class="text-muted">—</em>
                @endif
              </td>

              {{-- Período --}}
              <td class="text-nowrap">
                @if($registro->periodo_from && $registro->periodo_to)
                  {{ \Carbon\Carbon::parse($registro->periodo_from)->format('d/m/y') }}
                  <span class="text-muted">→</span>
                  {{ \Carbon\Carbon::parse($registro->periodo_to)->format('d/m/y') }}
                @else
                  <em class="text-muted">—</em>
                @endif
              </td>

              {{-- Canales --}}
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
                  <em class="text-muted">—</em>
                @endif
              </td>

              {{-- Tamaño --}}
              <td class="d-none d-xl-table-cell text-nowrap">
                @if($registro->size_bytes)
                  {{ number_format($registro->size_bytes / 1024, 0) }} KB
                @else
                  <em class="text-muted">—</em>
                @endif
              </td>

              {{-- Fecha --}}
              <td class="text-nowrap">
                @if($registro->generated_at)
                  <div>{{ $registro->generated_at->format('d/m/y') }}</div>
                  <div class="text-muted" style="font-size:.75rem;">{{ $registro->generated_at->format('H:i') }}</div>
                @elseif(in_array($registro->status, ['pending','processing']))
                  <em class="text-muted" style="font-size:.75rem;">{{ __('en proceso') }}</em>
                @else
                  <em class="text-muted">—</em>
                @endif
              </td>

              {{-- Acciones --}}
              <td>
                <div class="d-flex gap-1">
                  @if($registro->status === 'completed')
                    <a href="{{ route('informes.download', $registro) }}"
                       class="btn btn-sm btn-success py-0 px-2"
                       title="{{ __('Descargar') }}">
                      <i class="fas fa-download"></i>
                    </a>
                  @else
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" disabled title="{{ __('No disponible') }}">
                      <i class="fas fa-download"></i>
                    </button>
                  @endif

                  <form method="POST"
                        action="{{ route('informes.destroy', $registro) }}"
                        onsubmit="return confirm('{{ __('¿Eliminar este registro?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="{{ __('Eliminar') }}">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>

            </tr>

            {{-- Mensaje de error expandido — solo para admins --}}
            @if($registro->status === 'failed' && $registro->error_message && auth()->user()->admin)
              <tr class="table-danger">
                <td colspan="8" class="py-1 px-3">
                  <small class="text-danger"><i class="fas fa-info-circle me-1"></i>{{ $registro->error_message }}</small>
                </td>
              </tr>
            @endif

          @empty
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="mb-2" style="font-size:2.5rem;opacity:.2;"><i class="fas fa-file-pdf"></i></div>
                <div class="text-muted">{{ __('No hay informes registrados aún') }}</div>
                <a href="{{ route('informes.demanda') }}" class="btn btn-sm btn-primary mt-2">
                  <i class="fas fa-plus me-1"></i>{{ __('Generar el primero') }}
                </a>
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

@push('scripts')
<script>
// Auto-refresh si hay informes activos
@if($hayActivos)
(function () {
  const activeRows = document.querySelectorAll('[data-informe-id]');
  if (!activeRows.length) return;

  const CSRF = '{{ csrf_token() }}';

  async function pollRow(row) {
    const url = row.dataset.statusUrl;
    try {
      const r    = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF } });
      const data = await r.json();

      if (data.status === 'completed' || data.status === 'failed') {
        // Recargar la página completa para reflejar el nuevo estado
        window.location.reload();
      }
    } catch (_) { /* ignorar errores de red, se reintentará */ }
  }

  // Poll cada 5 s por fila activa
  activeRows.forEach(row => {
    setInterval(() => pollRow(row), 5000);
  });

  // Fallback: recarga completa a los 60 s en cualquier caso
  setTimeout(() => window.location.reload(), 60000);
})();
@endif

// Activar tooltips de Bootstrap para errores
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
  new bootstrap.Tooltip(el, { placement: 'top' });
});
</script>
@endpush

@endsection
