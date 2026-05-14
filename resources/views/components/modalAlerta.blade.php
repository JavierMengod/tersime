@props([
    'isEdit'      => false,
    'rule'        => null,
    'devicesList',
])

@php
    $action  = $isEdit ? route('reglas.update', $rule['id']) : route('reglas.guardar');
    $modalId = $isEdit ? "modal-rule-{$rule['id']}" : 'modal-rule-create';
    $title   = $isEdit ? __('Editar regla') : __('Nueva regla de alerta');

    $selectedDevices = old('devices', $rule['devices'] ?? []);
    $selectedMethods = old('methods', array_keys(array_filter($rule['methods'] ?? [])));
    $activeOp        = old('operator', $rule['operator'] ?? '>');
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <form method="POST" action="{{ $action }}" class="modal-content">
      @csrf
      @if($isEdit) @method('PUT') @endif

      {{-- CABECERA --}}
      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold" id="{{ $modalId }}-label">{{ $title }}</h5>
          <p class="text-muted small mb-0">
            {{ $isEdit ? __('Modifica los parámetros de esta regla') : __('Define cuándo y cómo quieres ser notificado') }}
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-3">

        {{-- ── SECCIÓN 1: Identificación ───────────────────────────────── --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">1</span>
            <span class="fw-semibold">{{ __('Nombre de la regla') }}</span>
          </div>
          <input type="text" name="name" class="form-control"
                 placeholder="{{ __('Ej: Alta demanda servidor principal') }}"
                 value="{{ old('name', $rule['name'] ?? '') }}">
        </div>

        <hr class="my-0 mb-4">

        {{-- ── SECCIÓN 2: Condición ────────────────────────────────────── --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">2</span>
            <span class="fw-semibold">{{ __('Condición de disparo') }}</span>
          </div>

          {{-- Fórmula visual --}}
          <div class="d-flex align-items-center flex-wrap gap-2 p-3 rounded mb-3"
               style="background:var(--background-color);border:1px dashed var(--border-color);">
            <span class="small text-muted">{{ __('Si') }}</span>
            <span class="badge bg-secondary">{{ __('valor del dispositivo') }}</span>
            <select name="operator" class="form-select form-select-sm w-auto"
                    style="min-width:5rem;">
              @foreach (['>' => '> (mayor)', '>=' => '≥ (mayor o igual)', '<' => '< (menor)', '<=' => '≤ (menor o igual)', '==' => '= (igual)', '!=' => '≠ (distinto)'] as $op => $label)
                <option value="{{ $op }}" {{ $activeOp === $op ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
            <div class="input-group input-group-sm" style="width:9rem;">
              <input type="number" name="value" step="0.01" min="0" class="form-control"
                     placeholder="0.00"
                     value="{{ old('value', $rule['value'] ?? '') }}">
              <span class="input-group-text">kWh</span>
            </div>
            <span class="small text-muted">{{ __('durante') }}</span>
            <div class="input-group input-group-sm" style="width:8rem;">
              <input type="number" name="for_duration" min="0" step="1" class="form-control"
                     placeholder="0"
                     value="{{ old('for_duration', $rule['for_duration'] ?? 0) }}">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>{{ __('Ventana 0 min = alerta instantánea en cuanto se detecte el valor.') }}
          </small>
        </div>

        <hr class="my-0 mb-4">

        {{-- ── SECCIÓN 3: Dispositivos ─────────────────────────────────── --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">3</span>
            <span class="fw-semibold">{{ __('Dispositivos monitorizados') }}</span>
          </div>

          @if($devicesList->isEmpty())
            <p class="text-muted small">{{ __('No tienes dispositivos habilitados.') }}</p>
          @else
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
              @foreach ($devicesList as $d)
                <div class="form-check py-1 px-3 rounded"
                     style="cursor:pointer;"
                     onmouseover="this.style.background='var(--hover-background)'"
                     onmouseout="this.style.background='transparent'">
                  <input class="form-check-input" type="checkbox"
                         name="devices[]"
                         value="{{ $d->id }}"
                         id="{{ $modalId }}-dev-{{ $d->id }}"
                         {{ in_array($d->id, $selectedDevices) ? 'checked' : '' }}>
                  <label class="form-check-label w-100 d-flex align-items-center gap-2"
                         for="{{ $modalId }}-dev-{{ $d->id }}"
                         style="cursor:pointer;">
                    <i class="fas fa-microchip text-muted small"></i>
                    {{ $d->nombre }}
                    <code class="ms-auto text-muted" style="font-size:.7rem;">{{ $d->influx_tag }}</code>
                  </label>
                </div>
              @endforeach
            </div>
          @endif
        </div>

        <hr class="my-0 mb-4">

        {{-- ── SECCIÓN 4: Canales de notificación ─────────────────────── --}}
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">4</span>
            <span class="fw-semibold">{{ __('Canales de notificación') }}</span>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-3">
            {{-- Telegram --}}
            <div>
              <input type="checkbox" class="btn-check" id="{{ $modalId }}-ch-telegram"
                     name="methods[]" value="telegram" autocomplete="off"
                     {{ in_array('telegram', $selectedMethods) ? 'checked' : '' }}
                     onchange="toggleTemplate('{{ $modalId }}', 'telegram', this.checked)">
              <label class="btn btn-outline-info d-flex align-items-center gap-2 px-3"
                     for="{{ $modalId }}-ch-telegram">
                <i class="fab fa-telegram"></i> Telegram
              </label>
            </div>

            {{-- Email --}}
            <div>
              <input type="checkbox" class="btn-check" id="{{ $modalId }}-ch-email"
                     name="methods[]" value="email" autocomplete="off"
                     {{ in_array('email', $selectedMethods) ? 'checked' : '' }}
                     onchange="toggleTemplate('{{ $modalId }}', 'email', this.checked)">
              <label class="btn btn-outline-warning d-flex align-items-center gap-2 px-3"
                     for="{{ $modalId }}-ch-email">
                <i class="fas fa-envelope"></i> {{ __('Correo') }}
              </label>
            </div>

            {{-- Discord --}}
            <div>
              <input type="checkbox" class="btn-check" id="{{ $modalId }}-ch-discord"
                     name="methods[]" value="discord" autocomplete="off"
                     {{ in_array('discord', $selectedMethods) ? 'checked' : '' }}
                     onchange="toggleTemplate('{{ $modalId }}', 'discord', this.checked)">
              <label class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3"
                     for="{{ $modalId }}-ch-discord">
                <i class="fab fa-discord"></i> Discord
              </label>
            </div>
          </div>

          {{-- Plantillas por canal (se muestran/ocultan dinámicamente) --}}
          <div id="{{ $modalId }}-tpl-telegram"
               style="{{ in_array('telegram', $selectedMethods) ? '' : 'display:none' }}">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-info) !important;border-left-width:3px !important;">
              <label class="small fw-semibold text-info mb-2 d-flex align-items-center gap-1">
                <i class="fab fa-telegram"></i> {{ __('Mensaje Telegram') }}
                <span class="text-muted fw-normal">({{ __('opcional, si vacío usa texto automático') }})</span>
              </label>
              <textarea name="template_telegram" class="form-control form-control-sm" rows="2"
                        placeholder="{{ __('Ej: 🚨 Atención: consumo elevado en {dispositivo}') }}">{{ old('template_telegram', $rule['templates']['telegram'] ?? '') }}</textarea>
            </div>
          </div>

          <div id="{{ $modalId }}-tpl-email"
               style="{{ in_array('email', $selectedMethods) ? '' : 'display:none' }}">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-warning) !important;border-left-width:3px !important;">
              <label class="small fw-semibold text-warning mb-2 d-flex align-items-center gap-1">
                <i class="fas fa-envelope"></i> {{ __('Mensaje Correo') }}
                <span class="text-muted fw-normal">({{ __('opcional') }})</span>
              </label>
              <textarea name="template_email" class="form-control form-control-sm mb-2" rows="2"
                        placeholder="{{ __('Cuerpo del correo...') }}">{{ old('template_email', $rule['templates']['email'] ?? '') }}</textarea>
              <input type="email" name="recipient_email" class="form-control form-control-sm"
                     placeholder="{{ __('Destinatario: correo@ejemplo.com') }}"
                     value="{{ old('recipient_email', $rule['recipient_email'] ?? '') }}">
            </div>
          </div>

          <div id="{{ $modalId }}-tpl-discord"
               style="{{ in_array('discord', $selectedMethods) ? '' : 'display:none' }}">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-secondary) !important;border-left-width:3px !important;">
              <label class="small fw-semibold text-secondary mb-2 d-flex align-items-center gap-1">
                <i class="fab fa-discord"></i> {{ __('Mensaje Discord') }}
                <span class="text-muted fw-normal">({{ __('opcional') }})</span>
              </label>
              <textarea name="template_discord" class="form-control form-control-sm" rows="2"
                        placeholder="{{ __('Mensaje para el webhook de Discord...') }}">{{ old('template_discord', $rule['templates']['discord'] ?? '') }}</textarea>
            </div>
          </div>

        </div>

      </div>

      {{-- FOOTER --}}
      <div class="modal-footer border-0 pt-0 justify-content-end gap-2">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
        @if ($isEdit)
          <button type="button" class="btn btn-outline-danger btn-sm"
                  onclick="if(confirm('{{ __('¿Eliminar esta regla definitivamente?') }}')) { document.getElementById('delete-rule-{{ $rule['id'] }}').submit(); }">
            <i class="fas fa-trash-alt"></i>
          </button>
        @endif
        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
          <i class="fas fa-save"></i> {{ __('Guardar') }}
        </button>
      </div>
    </form>

    @if ($isEdit)
      <form id="delete-rule-{{ $rule['id'] }}" method="POST"
            action="{{ route('reglas.destroy', $rule['id']) }}">
        @csrf @method('DELETE')
      </form>
    @endif
  </div>
</div>

@once
@push('scripts')
<script>
function toggleTemplate(modalId, channel, show) {
    var el = document.getElementById(modalId + '-tpl-' + channel);
    if (el) el.style.display = show ? '' : 'none';
}
</script>
@endpush
@endonce
