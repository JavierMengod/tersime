@extends('layouts.plantilla')

@section('title', __('Programación de Informes'))

@section('contenido')
@php
    $devicesList = auth()->user()->dispositivos->map(function($d) {
        return ['id' => $d->id, 'nombre' => $d->nombre ?? $d->name ?? $d->influx_tag ?? 'Dispositivo'];
    })->values()->toArray();
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Programación de Informes') }}</h2>
        <p class="text-muted mb-0 small">{{ __('Historial de informes automáticos configurados') }}</p>
    </div>
    <button class="btn btn-primary" id="btn-nueva-programacion" data-bs-toggle="modal" data-bs-target="#modal-schedule">
        <i class="bi bi-plus-lg me-1"></i>{{ __('Nueva programación') }}
    </button>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Nombre') }}</th>
                        <th>{{ __('Frecuencia') }}</th>
                        <th class="d-none d-md-table-cell">{{ __('Dispositivos') }}</th>
                        <th>{{ __('Canales') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Última ejec.') }}</th>
                        <th class="d-none d-sm-table-cell">{{ __('Próxima ejec.') }}</th>
                        <th>{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($informes as $informe)
                    @php $proxima = $informe->proximaEjecucion(); @endphp
                    <tr>
                        <td class="fw-semibold">{{ $informe->nombre }}</td>

                        <td class="text-nowrap">
                            {{ $informe->formatearFrecuencia() }}
                            @if($informe->hora_inicio && in_array($informe->tipo_periodo, ['dias','meses']))
                                <br><span class="text-muted" style="font-size:.75rem;"><i class="bi bi-clock me-1"></i>{{ $informe->hora_inicio }}</span>
                            @endif
                        </td>

                        <td style="max-width:150px;" class="d-none d-md-table-cell">
                            @if($informe->dispositivos->isNotEmpty())
                                <span class="text-truncate d-block"
                                      title="{{ $informe->dispositivos->map(fn($d) => $d->nombre ?? $d->name)->implode(', ') }}">
                                    {{ $informe->dispositivos->map(fn($d) => $d->nombre ?? $d->name)->implode(', ') }}
                                </span>
                            @else
                                <em class="text-muted">—</em>
                            @endif
                        </td>

                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                @if($informe->telegram)
                                    <i class="fab fa-telegram text-info" title="Telegram" style="font-size:1rem;"></i>
                                @endif
                                @if($informe->correo)
                                    <i class="fas fa-envelope" title="{{ __('Correo') }}" style="font-size:1rem;color:#ffc107;"></i>
                                @endif
                                @if($informe->discord)
                                    <i class="fab fa-discord" title="Discord" style="font-size:1rem;color:#7289da;"></i>
                                @endif
                                @if(!$informe->telegram && !$informe->correo && !$informe->discord)
                                    <em class="text-muted">—</em>
                                @endif
                            </div>
                        </td>

                        <td class="text-nowrap text-muted d-none d-lg-table-cell">
                            {{ $informe->last_run_at ? $informe->last_run_at->format('d/m/y H:i') : '—' }}
                        </td>

                        <td class="text-nowrap d-none d-sm-table-cell {{ ($proxima && $proxima->isPast() && $informe->activo) ? 'text-danger fw-semibold' : 'text-muted' }}">
                            @if($proxima)
                                {{ $proxima->format('d/m/y H:i') }}
                            @elseif($informe->activo)
                                {{ __('Pendiente') }}
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            <div class="d-flex gap-1">
                                <form action="{{ route('programaciones.toggle', $informe) }}" method="POST" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm {{ $informe->activo ? 'btn-outline-secondary' : 'btn-outline-success' }} py-0 px-2"
                                            title="{{ $informe->activo ? __('Deshabilitar') : __('Habilitar') }}">
                                        {{ $informe->activo ? __('Deshabilitar') : __('Habilitar') }}
                                    </button>
                                </form>
                                <button class="btn btn-sm btn-outline-primary py-0 px-2 btn-editar-programacion"
                                        data-bs-toggle="modal" data-bs-target="#modal-schedule"
                                        data-id="{{ $informe->id }}"
                                        data-nombre="{{ $informe->nombre }}"
                                        data-tipo-periodo="{{ $informe->tipo_periodo ?? 'horas' }}"
                                        data-valor-periodo="{{ $informe->valor_periodo ?? 1 }}"
                                        data-dispositivos="{{ $informe->dispositivos->pluck('id')->join(',') }}"
                                        data-telegram="{{ $informe->telegram ? '1' : '0' }}"
                                        data-correo="{{ $informe->correo ? '1' : '0' }}"
                                        data-discord="{{ $informe->discord ? '1' : '0' }}"
                                        data-correo-destino="{{ $informe->correo_destino ?? '' }}"
                                        data-hora-inicio="{{ $informe->hora_inicio ?? '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('programaciones.destroy', $informe) }}"
                                      onsubmit="return confirm('{{ __('¿Eliminar esta programación?') }}');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="mb-2" style="font-size:2.5rem;opacity:.2;"><i class="bi bi-calendar-check"></i></div>
                            <div class="text-muted">{{ __('No hay programaciones creadas aún') }}</div>
                            <button class="btn btn-outline-primary btn-sm mt-2" data-bs-toggle="modal"
                                    data-bs-target="#modal-schedule" id="btn-nueva-programacion-empty">
                                {{ __('Crear primera programación') }}
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($informes->hasPages())
    <div class="mt-3 d-flex justify-content-between align-items-center">
        <small class="text-muted">
            {{ __('Mostrando') }} {{ $informes->firstItem() }}–{{ $informes->lastItem() }} {{ __('de') }} {{ $informes->total() }}
        </small>
        {{ $informes->links() }}
    </div>
@endif


{{-- ── Modal ────────────────────────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-schedule" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-schedule-title">{{ __('Nueva programación') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="form-schedule" method="POST" action="{{ route('programaciones.store') }}">
                @csrf
                <input type="hidden" name="_method" id="form-schedule-method" value="POST">

                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Nombre --}}
                        <div class="col-12">
                            <label class="form-label" for="field-nombre">{{ __('Nombre') }}</label>
                            <input type="text" name="nombre" id="field-nombre" class="form-control" required
                                   placeholder="{{ __('Ej: Informe semanal almacén') }}">
                        </div>

                        {{-- Periodicidad --}}
                        <div class="col-12">
                            <label class="form-label">{{ __('Repetir cada') }}</label>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <input type="number" name="valor_periodo" id="field-valor-periodo"
                                       class="form-control text-center" min="1" step="1" value="1" required
                                       style="max-width:90px;">
                                <select name="tipo_periodo" id="field-tipo-periodo" class="form-select" required
                                        style="max-width:140px;">
                                    <option value="horas">{{ __('hora(s)') }}</option>
                                    <option value="dias">{{ __('día(s)') }}</option>
                                    <option value="meses">{{ __('mes(es)') }}</option>
                                </select>
                                <span class="text-muted small">→ {{ __('cada') }} <strong id="preview-periodicidad">1 hora</strong></span>
                            </div>
                        </div>

                        {{-- Hora de inicio (solo para días o meses) --}}
                        <div class="col-12 d-none" id="row-hora-inicio">
                            <label class="form-label" for="field-hora-inicio">
                                {{ __('Hora de entrega') }}
                            </label>
                            <input type="time" name="hora_inicio" id="field-hora-inicio"
                                   class="form-control" style="max-width:140px;" value="09:00">
                            <div class="form-text">{{ __('El informe se generará a esta hora del día.') }}</div>
                        </div>

                        {{-- Dispositivos --}}
                        <div class="col-12">
                            <label class="form-label" for="field-dispositivos">{{ __('Dispositivos') }}</label>
                            <select name="dispositivos[]" id="field-dispositivos"
                                    class="form-select" multiple required style="min-height:110px;">
                                @foreach($devicesList as $d)
                                    <option value="{{ $d['id'] }}">{{ $d['nombre'] }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">{{ __('Ctrl/Cmd para seleccionar varios.') }}</div>
                        </div>

                        {{-- Canales --}}
                        <div class="col-12">
                            <label class="form-label">{{ __('Canales de notificación') }}</label>

                            {{-- Checkboxes ocultos reales --}}
                            <input type="checkbox" name="telegram" id="field-telegram" value="1" class="d-none">
                            <input type="checkbox" name="correo"   id="field-correo"   value="1" class="d-none">
                            <input type="checkbox" name="discord"  id="field-discord"  value="1" class="d-none">

                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-canal"
                                        data-target="field-telegram"
                                        data-canal="telegram"
                                        data-sin-config="{{ in_array('telegram', $canalesSinConfig) ? '1' : '0' }}">
                                    <i class="fab fa-telegram me-1" style="color:#29b6f6;"></i>Telegram
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-canal"
                                        data-target="field-correo"
                                        data-canal="correo"
                                        data-sin-config="{{ in_array('correo', $canalesSinConfig) ? '1' : '0' }}">
                                    <i class="fas fa-envelope me-1" style="color:#ffc107;"></i>{{ __('Correo') }}
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-canal"
                                        data-target="field-discord"
                                        data-canal="discord"
                                        data-sin-config="{{ in_array('discord', $canalesSinConfig) ? '1' : '0' }}">
                                    <i class="fab fa-discord me-1" style="color:#7289da;"></i>Discord
                                </button>
                            </div>

                            {{-- Aviso canal sin configurar --}}
                            <div id="aviso-canal" class="alert alert-warning alert-dismissible d-flex align-items-start gap-2 mt-2 mb-0 py-2 px-3 d-none" role="alert">
                                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                                <div id="aviso-canal-texto" class="small"></div>
                                <button type="button" class="btn-close btn-sm ms-auto p-1" onclick="this.parentElement.classList.add('d-none')"></button>
                            </div>
                        </div>

                        {{-- Correo destino (condicional) --}}
                        <div class="col-12 d-none" id="row-correo-destino">
                            <label class="form-label" for="field-correo-destino">
                                {{ __('Dirección de correo destino') }} <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="correo_destino" id="field-correo-destino"
                                   class="form-control" placeholder="ejemplo@dominio.com">
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-schedule">
                        {{ __('Crear programación') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('styles')
<style>
.btn-canal.active { border-color: var(--bs-primary); color: var(--bs-primary); background: rgba(var(--bs-primary-rgb),.08); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const form        = document.getElementById('form-schedule');
    const methodField = document.getElementById('form-schedule-method');
    const title       = document.getElementById('modal-schedule-title');
    const btnSubmit   = document.getElementById('btn-submit-schedule');
    const storeUrl    = "{{ route('programaciones.store') }}";

    // ── Preview periodicidad ──────────────────────────────────────────────────
    const inputValor = document.getElementById('field-valor-periodo');
    const selTipo    = document.getElementById('field-tipo-periodo');
    const preview    = document.getElementById('preview-periodicidad');

    const labels = {
        horas:  ['hora',  'horas'],
        dias:   ['día',   'días'],
        meses:  ['mes',   'meses'],
    };

    function actualizarPreview() {
        const v    = Math.max(1, parseInt(inputValor.value) || 1);
        const tipo = selTipo.value;
        const [s, p] = labels[tipo] || labels.horas;
        preview.textContent = v + ' ' + (v === 1 ? s : p);
        rowHoraInicio.classList.toggle('d-none', tipo === 'horas');
    }
    inputValor.addEventListener('input', actualizarPreview);
    selTipo.addEventListener('change', actualizarPreview);

    // ── Canales: toggle + aviso si no configurado ─────────────────────────────
    const checkCorreo     = document.getElementById('field-correo');
    const rowCorreoDest   = document.getElementById('row-correo-destino');
    const fieldCorreoDest = document.getElementById('field-correo-destino');
    const rowHoraInicio   = document.getElementById('row-hora-inicio');
    const fieldHoraInicio = document.getElementById('field-hora-inicio');
    const avisoCanal      = document.getElementById('aviso-canal');
    const avisoTexto      = document.getElementById('aviso-canal-texto');

    const nombresCanal = {
        telegram: 'Telegram',
        correo:   '{{ __("Correo") }}',
        discord:  'Discord',
    };
    const rutasConfig = {
        telegram: '{{ route("alertas.medios") }}',
        correo:   '{{ route("alertas.medios") }}',
        discord:  '{{ route("alertas.medios") }}',
    };

    document.querySelectorAll('.btn-canal').forEach(btn => {
        btn.addEventListener('click', function () {
            const chk     = document.getElementById(this.dataset.target);
            chk.checked   = !chk.checked;
            this.classList.toggle('active', chk.checked);

            if (this.dataset.target === 'field-correo') {
                rowCorreoDest.classList.toggle('d-none', !chk.checked);
                fieldCorreoDest.required = chk.checked;
                if (!chk.checked) fieldCorreoDest.value = '';
            }

            // Aviso si canal sin configurar y se activa
            if (chk.checked && this.dataset.sinConfig === '1') {
                const canal = this.dataset.canal;
                avisoTexto.innerHTML = '<strong>' + nombresCanal[canal] + '</strong> no está configurado. '
                    + '<a href="' + rutasConfig[canal] + '" class="alert-link">{{ __("Ir a Configuración") }} →</a>';
                avisoCanal.classList.remove('d-none');
            } else if (!chk.checked) {
                // Comprobar si quedan activos sin config
                const hayOtro = Array.from(document.querySelectorAll('.btn-canal.active'))
                    .some(b => b.dataset.sinConfig === '1');
                if (!hayOtro) avisoCanal.classList.add('d-none');
            }
        });
    });

    // ── Reset (modo crear) ────────────────────────────────────────────────────
    function resetForm() {
        form.reset();
        form.action       = storeUrl;
        methodField.value = 'POST';
        title.textContent    = "{{ __('Nueva programación') }}";
        btnSubmit.textContent = "{{ __('Crear programación') }}";

        inputValor.value = '1';
        selTipo.value    = 'horas';
        preview.textContent = '1 hora';

        Array.from(document.getElementById('field-dispositivos').options)
            .forEach(o => o.selected = false);

        document.querySelectorAll('.btn-canal').forEach(b => b.classList.remove('active'));
        ['field-telegram','field-correo','field-discord'].forEach(id => {
            document.getElementById(id).checked = false;
        });

        rowCorreoDest.classList.add('d-none');
        fieldCorreoDest.required = false;
        avisoCanal.classList.add('d-none');
        rowHoraInicio.classList.add('d-none');
    }

    document.querySelectorAll('#btn-nueva-programacion, #btn-nueva-programacion-empty')
        .forEach(btn => btn.addEventListener('click', resetForm));

    // ── Editar: poblar modal ──────────────────────────────────────────────────
    document.querySelectorAll('.btn-editar-programacion').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = this.dataset;

            title.textContent     = "{{ __('Editar programación') }}";
            btnSubmit.textContent = "{{ __('Guardar cambios') }}";
            form.action       = "{{ url('/programaciones') }}/" + d.id;
            methodField.value = 'PUT';

            document.getElementById('field-nombre').value        = d.nombre;
            inputValor.value = d.valorPeriodo;
            selTipo.value    = d.tipoPeriodo;
            actualizarPreview();
            fieldHoraInicio.value = d.horaInicio || '09:00';

            // Dispositivos
            const ids = d.dispositivos ? d.dispositivos.split(',').filter(Boolean) : [];
            Array.from(document.getElementById('field-dispositivos').options)
                .forEach(opt => opt.selected = ids.includes(opt.value));

            // Canales
            const estadoCanales = { 'field-telegram': d.telegram, 'field-correo': d.correo, 'field-discord': d.discord };
            document.querySelectorAll('.btn-canal').forEach(b => {
                const activo = estadoCanales[b.dataset.target] === '1';
                document.getElementById(b.dataset.target).checked = activo;
                b.classList.toggle('active', activo);
            });

            const usaCorreo = d.correo === '1';
            fieldCorreoDest.value    = d.correoDestino || '';
            rowCorreoDest.classList.toggle('d-none', !usaCorreo);
            fieldCorreoDest.required = usaCorreo;

            // Mostrar aviso si algún canal activo no está configurado
            const haySinConfig = Array.from(document.querySelectorAll('.btn-canal.active'))
                .some(b => b.dataset.sinConfig === '1');
            if (haySinConfig) {
                const canalesMal = Array.from(document.querySelectorAll('.btn-canal.active'))
                    .filter(b => b.dataset.sinConfig === '1')
                    .map(b => '<strong>' + nombresCanal[b.dataset.canal] + '</strong>');
                avisoTexto.innerHTML = canalesMal.join(' y ') + ' no {{ Str::lower(__("están configurados")) }}. '
                    + '<a href="{{ route("alertas.medios") }}" class="alert-link">{{ __("Ir a Configuración") }} →</a>';
                avisoCanal.classList.remove('d-none');
            } else {
                avisoCanal.classList.add('d-none');
            }
        });
    });
})();
</script>
@endpush
@endsection
