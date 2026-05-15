@extends('layouts.plantilla')

@section('title', __('Informe Bajo Demanda'))

@section('contenido')
  <div class="container-fluid px-2">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">{{ __('Informe Bajo Demanda') }}</h2>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
      <div class="card-header bg-white fw-bold">{{ __('Filtros') }}</div>
      <div class="card-body">
        <form id="formGenerar" method="POST" action="{{ route('informes.demanda.store') }}">
          @csrf
          <div class="row gy-3 align-items-end">

            <div class="col-12 col-md-3">
              <label for="fromDate" class="form-label">{{ __('Desde:') }}</label>
              <input type="date" id="fromDate" name="fromDate" class="form-control">
            </div>

            <div class="col-12 col-md-3">
              <label for="toDate" class="form-label">{{ __('Hasta:') }}</label>
              <input type="date" id="toDate" name="toDate" class="form-control">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label">{{ __('Dispositivos:') }}</label>
              <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                        type="button"
                        id="dropdownDispositivos"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-haspopup="true">
                  {{ __('Seleccionar...') }}
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="dropdownDispositivos">
                  @foreach ($dispositivos as $d)
                    <li>
                      <a class="dropdown-item dispositivo-opcion"
                         href="#"
                         data-id="{{ $d->id }}"
                         data-alias="{{ $d->nombre }}">
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
                <span class="text-muted fst-italic">{{ __('Ninguno') }}</span>
              </div>
            </div>

            <div class="col-12 col-md-4">
              <label for="email" class="form-label">{{ __('Correo de destino:') }}</label>
              <input type="email" id="email" name="email" class="form-control" placeholder="correo@ejemplo.com">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">{{ __('Notificar por:') }}</label>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="correo" id="checkCorreo">
                <label class="form-check-label" for="checkCorreo">{{ __('Correo') }}</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="telegram" id="checkTelegram">
                <label class="form-check-label" for="checkTelegram">Telegram</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="discord" id="checkDiscord">
                <label class="form-check-label" for="checkDiscord">Discord</label>
              </div>
            </div>

            <div class="col-12 col-md-2 d-grid">
              <label class="form-label d-block">&nbsp;</label>
              <button id="btnGenerarInforme" type="button" class="btn btn-primary">
                {{ __('Generar Informe') }}
              </button>
            </div>

          </div>

          <input type="hidden" name="dispositivos" id="dispositivosInput">
          <input type="hidden" name="notificaciones" id="notificacionesInput">
        </form>
      </div>
    </div>

  </div>

<!-- Overlay animación -->
<div id="loadingOverlay" class="loading-overlay">
  <div class="spinner-border spinner--xl text-primary" role="status"></div>
  <div class="mt-3 fw-bold">{{ __('Generando informe, por favor espera...') }}</div>
</div>

@push('scripts')
<script>
const MSG_NINGUNO          = '{{ __('Ninguno') }}';
const MSG_ERROR_GENERACION = '{{ __('El informe se generó, pero no se pudo descargar automáticamente.') }}';
const MSG_ERROR_INFORME    = '{{ __('Error generando el informe: ') }}';
const CSRF_TOKEN           = '{{ csrf_token() }}';
</script>
<script src="{{ asset('assets/js/device-selector.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const from  = new Date();
    from.setDate(from.getDate() - 7);
    const fmtLocal = d => [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');
    document.getElementById('fromDate').value = fmtLocal(from);
    document.getElementById('toDate').value   = fmtLocal(today);

    const selector = new MultiDeviceSelector({
        containerId:  'dispositivosSeleccionados',
        itemSelector: '.dispositivo-opcion',
        msgEmpty:     MSG_NINGUNO,
        msgClass:     'text-muted fst-italic',
        getData:      el => ({ key: el.dataset.id, label: el.dataset.alias }),
        badgeClass:   'badge bg-primary d-flex align-items-center',
        closeBtnClass: 'btn-close btn-close-white btn-sm ms-2',
        onChange:     () => {},
    });

    document.getElementById('btnGenerarInforme').addEventListener('click', async () => {
        const notifs = ['checkCorreo', 'checkTelegram', 'checkDiscord']
            .filter(id => document.getElementById(id).checked)
            .map(id => id.replace('check', '').toLowerCase());

        document.getElementById('dispositivosInput').value  =
            JSON.stringify(selector.selections.map(s => ({ id: s.key, alias: s.label })));
        document.getElementById('notificacionesInput').value = JSON.stringify(notifs);

        const overlay = document.getElementById('loadingOverlay');
        const form    = document.getElementById('formGenerar');
        overlay.style.display = 'flex';

        try {
            const resp = await fetch(form.action, {
                method:  'POST',
                body:    new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF_TOKEN },
            });

            if (!resp.ok) throw new Error('Error en la generación del informe');

            const data = await resp.json();
            overlay.style.display = 'none';

            if (data.download_url) {
                window.location.href = data.download_url;
            } else {
                alert(MSG_ERROR_GENERACION);
            }
        } catch (err) {
            overlay.style.display = 'none';
            alert(MSG_ERROR_INFORME + err.message);
        }
    });
});
</script>
@endpush

@endsection
