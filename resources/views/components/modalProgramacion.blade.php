@props([
  'isEdit' => false,
  'devicesList' => [],
  'schedule' => null,
])

@php
  $scheduleId = null;
  $scheduleNombre = '';
  $schedulePeriodicidad = 24;
  $scheduleDispositivos = [];
  $scheduleTelegram = false;
  $scheduleCorreo = false;
  $scheduleDiscord = false;
  $scheduleCorreoDestino = null;
  $scheduleActivo = true;

  if (is_array($schedule)) {
      $scheduleId = $schedule['id'] ?? null;
      $scheduleNombre = $schedule['nombre'] ?? '';
      $schedulePeriodicidad = $schedule['periodicidad'] ?? 24;
      $scheduleDispositivos = $schedule['dispositivos'] ?? [];
      $scheduleTelegram = !empty($schedule['telegram']);
      $scheduleCorreo = !empty($schedule['correo']);
      $scheduleDiscord = !empty($schedule['discord']);
      $scheduleCorreoDestino = $schedule['correo_destino'] ?? null;
      $scheduleActivo = array_key_exists('activo', $schedule) ? (bool)$schedule['activo'] : true;
  } elseif (is_object($schedule)) {
      $scheduleId = $schedule->id ?? null;
      $scheduleNombre = $schedule->nombre ?? '';
      $schedulePeriodicidad = $schedule->periodicidad ?? 24;
      $scheduleDispositivos = $schedule->dispositivos ? $schedule->dispositivos->pluck('id')->toArray() : [];
      $scheduleTelegram = !empty($schedule->telegram);
      $scheduleCorreo = !empty($schedule->correo);
      $scheduleDiscord = !empty($schedule->discord);
      $scheduleCorreoDestino = $schedule->correo_destino ?? null;
      $scheduleActivo = isset($schedule->activo) ? (bool)$schedule->activo : true;
  }

  // Calcular tipo_periodo y valor_periodo para el formulario
  $tipoPeriodo = 'horas';
  $valorPeriodo = $schedulePeriodicidad;

  if ($schedulePeriodicidad >= 720 && $schedulePeriodicidad % 720 === 0) {
      $tipoPeriodo = 'meses';
      $valorPeriodo = $schedulePeriodicidad / 720;
  } elseif ($schedulePeriodicidad >= 24 && $schedulePeriodicidad % 24 === 0) {
      $tipoPeriodo = 'dias';
      $valorPeriodo = $schedulePeriodicidad / 24;
  }

  $modalId = $isEdit ? 'modal-schedule-' . ($scheduleId ?? '0') : 'modal-schedule-create';
  $formAction = $isEdit
      ? route('programaciones.update', $scheduleId ?? 0)
      : route('programaciones.store');

  $selectedDevices = old('dispositivos', $scheduleDispositivos ?? []);
  $oldTelegram = old('telegram', $scheduleTelegram);
  $oldCorreo = old('correo', $scheduleCorreo);
  $oldDiscord = old('discord', $scheduleDiscord);
  $oldActivo = old('activo', $scheduleActivo);
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ $isEdit ? __('Editar programación') : __('Nueva programación') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Cerrar') }}"></button>
      </div>

      <form method="POST" action="{{ $formAction }}">
        @csrf
        @if($isEdit)
          @method('PUT')
        @endif

        <div class="modal-body">
          <div class="row g-3">

            {{-- Activo --}}
            <div class="col-12 d-flex justify-content-between align-items-center">
              <label class="form-label mb-0">{{ __('Activo') }}</label>
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo"
                       id="{{ $modalId }}-activo" value="1"
                       {{ $oldActivo ? 'checked' : '' }}>
              </div>
            </div>

            {{-- Nombre --}}
            <div class="col-12">
              <label class="form-label">{{ __('Nombre') }}</label>
              <input name="nombre" type="text" class="form-control" required
                     value="{{ old('nombre', $scheduleNombre) }}">
            </div>

            {{-- Tipo de periodo --}}
            <div class="col-md-6">
              <label class="form-label">{{ __('Tipo de periodo') }}</label>
              <select name="tipo_periodo" class="form-select" required>
                <option value="horas" {{ $tipoPeriodo === 'horas' ? 'selected' : '' }}>{{ __('Horas') }}</option>
                <option value="dias" {{ $tipoPeriodo === 'dias' ? 'selected' : '' }}>{{ __('Días') }}</option>
                <option value="meses" {{ $tipoPeriodo === 'meses' ? 'selected' : '' }}>{{ __('Meses') }}</option>
              </select>
            </div>

            {{-- Valor del periodo --}}
            <div class="col-md-6">
              <label class="form-label">{{ __('Cantidad') }}</label>
              <input name="valor_periodo" type="number" class="form-control" min="1" step="1"
                     value="{{ old('valor_periodo', $valorPeriodo) }}" required>
            </div>

            {{-- Dispositivos --}}
            <div class="col-12">
              <label class="form-label">{{ __('Dispositivos') }}</label>
              <select name="dispositivos[]" class="form-select" multiple required>
                @foreach($devicesList as $d)
                  @php $did = is_array($d) ? ($d['id'] ?? null) : ($d->id ?? null); @endphp
                  <option value="{{ $did }}"
                          {{ in_array((string)$did, array_map('strval', $selectedDevices ?: [])) ? 'selected' : '' }}>
                    {{ is_array($d) ? ($d['nombre'] ?? $d['name'] ?? $did) : ($d->nombre ?? $d->name ?? $did) }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">{{ __('Mantén Ctrl/Cmd para seleccionar varios.') }}</div>
            </div>

            {{-- Canales --}}
            <div class="col-12">
              <label class="form-label">{{ __('Canales de notificación') }}</label>
              <div class="d-flex gap-3 align-items-center">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="{{ $modalId }}-tg" name="telegram" value="1"
                         {{ $oldTelegram ? 'checked' : '' }}>
                  <label class="form-check-label" for="{{ $modalId }}-tg">Telegram</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="{{ $modalId }}-mail" name="correo" value="1"
                         {{ $oldCorreo ? 'checked' : '' }}>
                  <label class="form-check-label" for="{{ $modalId }}-mail">{{ __('Correo') }}</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="{{ $modalId }}-dc" name="discord" value="1"
                         {{ $oldDiscord ? 'checked' : '' }}>
                  <label class="form-check-label" for="{{ $modalId }}-dc">Discord</label>
                </div>
              </div>
            </div>

            {{-- Correo destino --}}
            <div class="col-12">
              <label class="form-label">{{ __('Correo destino (si seleccionas Correo)') }}</label>
              <input name="correo_destino" type="email" class="form-control"
                     value="{{ old('correo_destino', $scheduleCorreoDestino) }}">
            </div>

          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
          <button type="submit" class="btn btn-primary">
            {{ $isEdit ? __('Guardar cambios') : __('Crear programación') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
