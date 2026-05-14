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
                       data-url="{{ $d->influx_tag }}">
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
      <div class="card-header bg-white fw-bold">{{ __('Previsualizar Grafana') }}</div>
      <div class="card-body">

        <div class="ratio ratio--grafana">
          <iframe id="grafanaIframe"
                  src=""
                  width="100%"
                  height="300"
                  frameborder="0"
                  loading="lazy"></iframe>
        </div>

        {{-- Leyenda con nombres amigables, en el mismo orden que las series de Grafana --}}
        <div id="grafanaLeyenda" class="d-none d-flex flex-wrap gap-3 mt-3 pt-3 border-top">
        </div>

      </div>
    </div>

  </div>

@push('scripts')
<script>
const GRAFANA_BASE   = '{{ $grafanaBaseUrl }}/d-solo/tersime-tr-embed/dashboard-initiot-embed';
const GRAFANA_PARAMS = 'orgId=1&timezone=browser&panelId=1&__feature.dashboardSceneSolo&theme={{ Auth::user()->theme ?? "light" }}';
const MSG_SIN_DISPOSITIVO = '{{ __('Debes seleccionar al menos un dispositivo') }}';
</script>
<script src="{{ asset('assets/js/grafana-utils.js') }}"></script>
<script src="{{ asset('assets/js/device-selector.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const iframe  = document.getElementById('grafanaIframe');
    const leyenda = document.getElementById('grafanaLeyenda');

    // Paleta en el mismo orden que la Classic Palette de Grafana
    const PALETTE = [
        '#7EB26D', '#EAB839', '#6ED0E0', '#EF843C',
        '#E24D42', '#1F78C1', '#BA43A9', '#705DA0',
        '#508642', '#CCA300',
    ];

    // ── Fechas por defecto ─────────────────────────────────────────────────────
    const today = new Date();
    const from  = new Date();
    from.setDate(today.getDate() - 7);
    document.getElementById('fromDate').value = formatLocalDate(from);
    document.getElementById('toDate').value   = formatLocalDate(today);

    // ── Selector de dispositivos ───────────────────────────────────────────────
    const selector = new MultiDeviceSelector({
        containerId:  'dispositivosSeleccionados',
        itemSelector: '.dispositivo-opcion',
        msgEmpty:     MSG_SIN_DISPOSITIVO,
        getData:      el => ({ key: el.dataset.url, label: el.textContent.trim() }),
        hideSelected: true,
        onChange:     () => {
            actualizarIframe();
            actualizarLeyenda(selector.selections);
        },
    });

    // ── Iframe ─────────────────────────────────────────────────────────────────
    function actualizarIframe() {
        const url = buildTiempoRealUrl(
            GRAFANA_BASE, GRAFANA_PARAMS,
            selector.selections,
            document.getElementById('fromDate').value,
            document.getElementById('toDate').value
        );
        iframe.src = url ?? '';
    }

    // ── Leyenda ────────────────────────────────────────────────────────────────
    function actualizarLeyenda(selections) {
        leyenda.innerHTML = '';

        if (!selections.length) {
            leyenda.classList.add('d-none');
            return;
        }

        selections.forEach((sel, i) => {
            const color = PALETTE[i % PALETTE.length];
            const item  = document.createElement('span');
            item.className = 'd-flex align-items-center gap-2 small fw-medium';

            const dot = document.createElement('span');
            dot.style.cssText = `width:12px;height:12px;border-radius:50%;background:${color};flex-shrink:0`;

            const texto = document.createElement('span');
            texto.textContent = sel.label;

            item.appendChild(dot);
            item.appendChild(texto);
            leyenda.appendChild(item);
        });

        leyenda.classList.remove('d-none');
    }

    document.getElementById('fromDate').addEventListener('change', actualizarIframe);
    document.getElementById('toDate').addEventListener('change',   actualizarIframe);
    actualizarIframe();
});
</script>
@endpush

@endsection
