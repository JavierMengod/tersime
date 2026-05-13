@extends('layouts.plantilla')

@section('title', __('Predicción de Consumos'))

@section('contenido')
  <div class="container-fluid px-2">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">{{ __('Predicción de Consumos') }}</h2>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">{{ __('Filtros') }}</div>
      <div class="card-body">
        <div class="row gy-3 align-items-end">

          <div class="col-12 col-md-4">
            <label for="fromDate" class="form-label">{{ __('Desde:') }}</label>
            <input type="date" id="fromDate" class="form-control">
          </div>

          <div class="col-12 col-md-4">
            <label for="toDate" class="form-label">{{ __('Hasta:') }}</label>
            <input type="date" id="toDate" class="form-control">
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label">{{ __('Dispositivo:') }}</label>
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                      type="button"
                      id="dropdownDispositivo"
                      data-bs-toggle="dropdown"
                      aria-expanded="false">
                {{ __('Seleccionar...') }}
              </button>
              <ul class="dropdown-menu w-100" aria-labelledby="dropdownDispositivo">
                @foreach ($dispositivos as $d)
                  <li>
                    <a class="dropdown-item dispositivo-opcion"
                       href="#"
                       data-url="{{ $d->URL }}">
                      {{ $d->nombre }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          </div>

        </div>

        <div class="mt-3">
          <div id="dispositivoSeleccionado" class="d-flex flex-wrap gap-1">
            <span class="text-danger fst-italic">{{ __('Debes seleccionar al menos un dispositivo') }}</span>
          </div>
        </div>

      </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">{{ __('Previsualizar Grafana') }}</div>
      <div class="card-body bg-light">
        <div class="ratio ratio--grafana">
          <iframe id="grafanaIframe"
                  src=""
                  width="100%"
                  height="300"
                  frameborder="0"
                  loading="lazy"></iframe>
        </div>
      </div>
    </div>

  </div>

@push('scripts')
<script>
const GRAFANA_BASE   = '{{ config('app.grafana_base_url') }}/d-solo/eegznxsjl47i8b/dashboard-initiot';
const GRAFANA_PARAMS = 'orgId=1&timezone=browser&panelId=4&__feature.dashboardSceneSolo&theme={{ Auth::user()->theme ?? "light" }}';
const MSG_SIN_DISPOSITIVO = '{{ __('Debes seleccionar al menos un dispositivo') }}';
</script>
<script src="{{ asset('js/grafana-utils.js') }}"></script>
<script src="{{ asset('js/device-selector.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const iframe = document.getElementById('grafanaIframe');

    const today = new Date();
    const from  = new Date();
    from.setDate(today.getDate() - 7);
    const to = new Date();
    to.setDate(today.getDate() + 14);
    document.getElementById('fromDate').value = formatLocalDate(from);
    document.getElementById('toDate').value   = formatLocalDate(to);

    const selector = new SingleDeviceSelector({
        containerId:  'dispositivoSeleccionado',
        itemSelector: '.dispositivo-opcion',
        msgEmpty:     MSG_SIN_DISPOSITIVO,
        getData:      el => ({ key: el.dataset.url, label: el.textContent.trim() }),
        onChange:     () => actualizarIframe(),
    });

    function actualizarIframe() {
        if (!selector.selection) { iframe.src = ''; return; }
        const w = iframe.clientWidth  || document.documentElement.clientWidth;
        const h = iframe.clientHeight || Math.round(window.innerHeight * 0.4);
        iframe.src = buildPrediccionUrl(
            GRAFANA_BASE, GRAFANA_PARAMS,
            selector.selection,
            document.getElementById('fromDate').value,
            document.getElementById('toDate').value,
            w, h
        );
    }

    document.getElementById('fromDate').addEventListener('change', actualizarIframe);
    document.getElementById('toDate').addEventListener('change', actualizarIframe);
    window.addEventListener('resize', debounce(actualizarIframe, 250));
    actualizarIframe();
});
</script>
@endpush

@endsection
