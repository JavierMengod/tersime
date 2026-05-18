@extends('layouts.plantilla')

@section('title', __('Informe Bajo Demanda'))

@section('contenido')
<div class="container-fluid px-2">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0">{{ __('Informe Bajo Demanda') }}</h2>
      <p class="text-muted mb-0 small">{{ __('Genera un PDF con el consumo del período elegido') }}</p>
    </div>
    <a href="{{ route('informes.registro') }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-history me-1"></i>{{ __('Historial') }}
    </a>
  </div>

  {{-- Alerta de error inline --}}
  <div id="alertError" class="alert alert-danger alert-dismissible d-none mb-3" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <span id="alertErrorMsg"></span>
    <button type="button" class="btn-close" onclick="document.getElementById('alertError').classList.add('d-none')"></button>
  </div>

  <form id="formGenerar">
    @csrf
    <div class="row g-3">

      {{-- Período --}}
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold small text-uppercase text-muted">
            <i class="fas fa-calendar-alt me-2 text-primary"></i>{{ __('Período') }}
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="fromDate" class="form-label small fw-semibold">{{ __('Desde') }}</label>
              <input type="date" id="fromDate" name="fromDate" class="form-control" required>
            </div>
            <div>
              <label for="toDate" class="form-label small fw-semibold">{{ __('Hasta') }}</label>
              <input type="date" id="toDate" name="toDate" class="form-control" required>
            </div>
          </div>
        </div>
      </div>

      {{-- Dispositivos --}}
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold small text-uppercase text-muted">
            <i class="fas fa-microchip me-2 text-primary"></i>{{ __('Dispositivos') }}
          </div>
          <div class="card-body">
            <div class="dropdown mb-2">
              <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                      type="button"
                      id="dropdownDispositivos"
                      data-bs-toggle="dropdown"
                      aria-expanded="false">
                <span id="btnDropLabel">{{ __('Seleccionar...') }}</span>
              </button>
              <ul class="dropdown-menu w-100" aria-labelledby="dropdownDispositivos">
                @foreach ($dispositivos as $d)
                  <li>
                    <a class="dropdown-item dispositivo-opcion"
                       href="#"
                       data-id="{{ $d->id }}"
                       data-alias="{{ $d->nombre }}">
                      <i class="fas fa-check me-2 text-primary opcion-check" style="visibility:hidden"></i>{{ $d->nombre }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
            <div id="dispositivosSeleccionados" class="d-flex flex-wrap gap-1 min-height-badge">
              <span class="text-muted fst-italic small" id="msgNinguno">{{ __('Ninguno seleccionado') }}</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Notificaciones --}}
      <div class="col-12 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold small text-uppercase text-muted">
            <i class="fas fa-bell me-2 text-primary"></i>{{ __('Notificaciones') }}
          </div>
          <div class="card-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="correo" id="checkCorreo">
              <label class="form-check-label" for="checkCorreo">
                <i class="fas fa-envelope me-1 text-warning"></i>{{ __('Correo electrónico') }}
              </label>
            </div>
            <div id="emailWrap" class="mb-3 d-none">
              <input type="email" id="email" name="email" class="form-control form-control-sm"
                     placeholder="{{ __('destinatario@ejemplo.com') }}">
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" value="telegram" id="checkTelegram">
              <label class="form-check-label" for="checkTelegram">
                <i class="fab fa-telegram me-1 text-info"></i>Telegram
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" value="discord" id="checkDiscord">
              <label class="form-check-label" for="checkDiscord">
                <i class="fab fa-discord me-1 text-secondary"></i>Discord
              </label>
            </div>
          </div>
        </div>
      </div>

    </div>

    <input type="hidden" name="dispositivos" id="dispositivosInput">
    <input type="hidden" name="notificaciones" id="notificacionesInput">

    <div class="mt-3 text-end">
      <button id="btnGenerarInforme" type="button" class="btn btn-primary px-4">
        <i class="fas fa-file-pdf me-2"></i>{{ __('Generar Informe') }}
      </button>
    </div>
  </form>

</div>

{{-- ── Panel flotante de progreso (no bloqueante) ───────────────────────────── --}}
<div id="informeManager" style="display:none; position:fixed; bottom:1.5rem; right:1.5rem;
     width:360px; background:#1e293b; color:#fff; border-radius:12px;
     box-shadow:0 8px 32px rgba(0,0,0,.4); z-index:1050; overflow:hidden; font-size:.82rem;">

  <div style="padding:12px 16px; display:flex; justify-content:space-between; align-items:center;
              border-bottom:1px solid rgba(255,255,255,.12); font-weight:600;">
    <span><i class="fas fa-file-pdf me-2"></i>{{ __('Informes') }}</span>
    <button id="btnManagerClose" class="btn-close btn-close-white btn-sm" style="opacity:.7"></button>
  </div>

  <div id="informeManagerList" style="max-height:300px; overflow-y:auto;"></div>
</div>

@push('scripts')
<script>
const CSRF_TOKEN  = '{{ csrf_token() }}';
const MSG_NINGUNO = '{{ __('Ninguno seleccionado') }}';
const ROUTE_STORE = '{{ route('informes.demanda.store') }}';
</script>
<script src="{{ asset('assets/js/device-selector.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {

  // ── Defaults de fechas ──────────────────────────────────────────────────────
  const today = new Date();
  const from  = new Date(); from.setDate(from.getDate() - 7);
  const fmt   = d => [d.getFullYear(), String(d.getMonth()+1).padStart(2,'0'), String(d.getDate()).padStart(2,'0')].join('-');
  document.getElementById('fromDate').value = fmt(from);
  document.getElementById('toDate').value   = fmt(today);

  // ── Email condicional al marcar correo ──────────────────────────────────────
  document.getElementById('checkCorreo').addEventListener('change', function () {
    document.getElementById('emailWrap').classList.toggle('d-none', !this.checked);
  });

  // ── Selector de dispositivos ────────────────────────────────────────────────
  const selector = new MultiDeviceSelector({
    containerId:   'dispositivosSeleccionados',
    itemSelector:  '.dispositivo-opcion',
    msgEmpty:      MSG_NINGUNO,
    msgClass:      'text-muted fst-italic small',
    getData:       el => ({ key: el.dataset.id, label: el.dataset.alias }),
    badgeClass:    'badge bg-primary d-flex align-items-center',
    closeBtnClass: 'btn-close btn-close-white btn-sm ms-2',
    onChange: (sel) => {
      const label = sel.length ? `${sel.length} ${sel.length === 1 ? 'dispositivo' : 'dispositivos'}` : '{{ __('Seleccionar...') }}';
      document.getElementById('btnDropLabel').textContent = label;
    },
  });

  // ── Gestión del panel flotante ──────────────────────────────────────────────
  const manager   = document.getElementById('informeManager');
  const managerList = document.getElementById('informeManagerList');
  let   queuedJobs = []; // [{id, statusUrl, startTime, status, timer}]

  document.getElementById('btnManagerClose').addEventListener('click', () => {
    // Ocultar panel pero continuar polling en background
    manager.style.display = 'none';
  });

  function renderManager() {
    if (queuedJobs.length === 0) { manager.style.display = 'none'; return; }
    manager.style.display = 'block';
    managerList.innerHTML = '';
    queuedJobs.forEach(job => {
      const el = document.createElement('div');
      el.id = `job-${job.id}`;
      el.style.cssText = 'padding:12px 16px; border-bottom:1px solid rgba(255,255,255,.08);';
      el.innerHTML = jobHTML(job);
      managerList.appendChild(el);
    });
  }

  function jobHTML(job) {
    const elapsed = Math.round((Date.now() - job.startTime) / 1000);
    const mins    = Math.floor(elapsed / 60);
    const secs    = elapsed % 60;
    const timeStr = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;

    if (job.status === 'completed') {
      return `<div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="badge bg-success me-1"><i class="fas fa-check"></i></span>
          <strong>{{ __('Informe listo') }}</strong>
          <div class="text-white-50" style="font-size:.75rem;">${timeStr}</div>
        </div>
        <a href="${job.downloadUrl}" class="btn btn-success btn-sm py-0 px-2">
          <i class="fas fa-download me-1"></i>PDF
        </a>
      </div>`;
    }

    if (job.status === 'failed') {
      return `<div>
        <span class="badge bg-danger me-1"><i class="fas fa-times"></i></span>
        <strong>{{ __('Error al generar') }}</strong>
        <div class="text-danger" style="font-size:.75rem;">${job.error || '{{ __('Error desconocido') }}'}</div>
        <div class="text-white-50" style="font-size:.75rem;">${timeStr} transcurridos</div>
      </div>`;
    }

    const statusLabel = job.status === 'processing'
      ? '{{ __('Generando PDF...') }}'
      : '{{ __('En cola...') }}';

    return `<div class="d-flex justify-content-between align-items-center">
      <div>
        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
        <strong>${statusLabel}</strong>
        <div class="text-white-50" style="font-size:.75rem;">${timeStr} transcurridos</div>
      </div>
    </div>`;
  }

  function pollJob(job) {
    job.timer = setTimeout(async () => {
      try {
        const r = await fetch(job.statusUrl, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF_TOKEN },
        });
        const data = await r.json();
        job.status = data.status;

        if (data.status === 'completed' && data.download_url) {
          job.downloadUrl = data.download_url;
          clearTimeout(job.timer);
          renderManager();
          // Auto-descarga
          const link = document.createElement('a');
          link.href  = data.download_url;
          link.click();
        } else if (data.status === 'failed') {
          job.error = data.error || null;
          clearTimeout(job.timer);
          renderManager();
        } else {
          renderManager();
          pollJob(job); // sigue esperando
        }
      } catch (err) {
        job.status = 'failed';
        job.error  = err.message;
        renderManager();
      }
    }, 4000);
  }

  // ── Error inline ─────────────────────────────────────────────────────────────
  function showError(msg) {
    const alert = document.getElementById('alertError');
    document.getElementById('alertErrorMsg').textContent = msg;
    alert.classList.remove('d-none');
    alert.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ── Submit ────────────────────────────────────────────────────────────────────
  document.getElementById('btnGenerarInforme').addEventListener('click', async () => {
    document.getElementById('alertError').classList.add('d-none');

    const notifs = ['checkCorreo','checkTelegram','checkDiscord']
      .filter(id => document.getElementById(id).checked)
      .map(id => id.replace('check','').toLowerCase());

    const email = document.getElementById('checkCorreo').checked
      ? document.getElementById('email').value.trim()
      : '';

    if (selector.selections.length === 0) {
      showError('{{ __('Selecciona al menos un dispositivo.') }}'); return;
    }

    document.getElementById('dispositivosInput').value   = JSON.stringify(selector.selections.map(s => ({ id: s.key, alias: s.label })));
    document.getElementById('notificacionesInput').value = JSON.stringify(notifs);

    const btn = document.getElementById('btnGenerarInforme');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>{{ __('Enviando...') }}';

    try {
      const fd   = new FormData(document.getElementById('formGenerar'));
      if (email) fd.set('email', email);

      const resp = await fetch(ROUTE_STORE, {
        method:  'POST',
        body:    fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF_TOKEN },
      });

      const data = await resp.json();

      if (!resp.ok) {
        throw new Error(data.error || 'HTTP ' + resp.status);
      }

      if (data.queued && data.status_url) {
        const job = {
          id:         data.informe_id,
          statusUrl:  data.status_url,
          startTime:  Date.now(),
          status:     'pending',
          error:      null,
          downloadUrl: null,
          timer:      null,
        };
        queuedJobs.push(job);
        renderManager();
        pollJob(job);
      } else {
        throw new Error('{{ __('Respuesta inesperada del servidor') }}');
      }
    } catch (err) {
      showError(err.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-file-pdf me-2"></i>{{ __('Generar Informe') }}';
    }
  });

});
</script>
@endpush

@endsection
