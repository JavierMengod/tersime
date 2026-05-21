@extends('layouts.plantilla')

@section('title', __('Monitorización en tiempo real'))

@section('contenido')
  <div class="container-fluid px-2">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">{{ __('Monitorización en tiempo real') }}</h2>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">{{ __('Filtros') }}</div>
      <div class="card-body">
        <div class="row gy-3 align-items-end">

          <div class="col-12 col-md-3">
            <label for="fromDate" class="form-label">{{ __('Desde:') }}</label>
            <input type="date" id="fromDate" class="form-control">
          </div>

          <div class="col-12 col-md-3">
            <label for="toDate" class="form-label">{{ __('Hasta:') }}</label>
            <input type="date" id="toDate" class="form-control">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">{{ __('Dispositivos:') }}</label>
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                      type="button"
                      id="dropdownDispositivos"
                      data-bs-toggle="dropdown"
                      aria-expanded="false">
                {{ __('Seleccionar...') }}
              </button>
              <ul class="dropdown-menu w-100" aria-labelledby="dropdownDispositivos">
                @foreach ($dispositivos as $d)
                  <li>
                    <a class="dropdown-item dispositivo-opcion"
                       href="#"
                       data-url="{{ $d->etiqueta_influx }}">
                      {{ $d->nombre }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          </div>

          <div class="col-12 col-md-2">
            <label class="form-label d-block">&nbsp;</label>
            <div id="dispositivosSeleccionados" class="d-flex flex-wrap gap-1">
              <span class="text-muted fst-italic">{{ __('Debes seleccionar al menos un dispositivo') }}</span>
            </div>
          </div>

        </div>
      </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
      <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
        <span>{{ __('Previsualizar Grafana') }}</span>
        <button data-bs-toggle="modal" data-bs-target="#modal-grafana-zoom"
                class="btn btn-sm btn-outline-secondary"
                title="{{ __('Abrir en pantalla completa') }}">
          <i class="fas fa-expand-alt"></i>
        </button>
      </div>
      <div class="card-body">

        <div class="ratio ratio--grafana">
          <iframe id="grafanaIframe"
                  src=""
                  width="100%"
                  height="100%"
                  frameborder="0"
                  loading="lazy"></iframe>
        </div>

        {{-- Leyenda con nombres amigables, en el mismo orden que las series de Grafana --}}
        <div id="grafanaLeyenda" class="d-none d-flex flex-wrap gap-3 mt-3 pt-3 border-top">
        </div>

      </div>
    </div>

  </div>

{{-- ── Modal zoom Grafana ────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-grafana-zoom" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-grafana-zoom">
    <div class="modal-content h-100">
      <div class="modal-header py-2 border-bottom-0">
        <span class="fw-semibold small">{{ __('Previsualizar Grafana') }}</span>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0 d-flex flex-column">
        <iframe id="grafanaZoomIframe" src="" frameborder="0" loading="lazy"
                class="flex-grow-1 w-100"></iframe>
      </div>
    </div>
  </div>
</div>

@push('styles')
<style>
@media (min-width: 576px) {
    #modal-grafana-zoom .modal-dialog { height: 88vh; margin-top: calc((100vh - 88vh) / 2); }
    #modal-grafana-zoom .modal-content { height: 100%; }
}
</style>
@endpush

@push('scripts')
<script>
const GRAFANA_BASE        = '/grafana/d-solo/eegznxsjl47i8b/dashboard-initiot';
const GRAFANA_PARAMS      = 'orgId=1&timezone=browser&panelId=1&__feature.dashboardSceneSolo&theme={{ Auth::user()->theme ?? "light" }}';
const MSG_SIN_DISPOSITIVO = '{{ __('Debes seleccionar al menos un dispositivo') }}';
</script>
<script src="{{ asset('assets/js/grafana-utils.js') }}"></script>
<script src="{{ asset('assets/js/device-selector.js') }}"></script>
<script src="{{ asset('assets/js/tiempo-real.js') }}"></script>
@endpush

@endsection
