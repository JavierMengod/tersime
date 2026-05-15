{{-- ── Modal Editar Regla (único, se puebla via JS) ─────────────────────────── --}}
<div class="modal fade" id="modal-rule-edit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
    <form id="form-rule-edit" method="POST" class="modal-content">
      @csrf
      @method('PUT')

      <div class="modal-header border-0 pb-0">
        <div>
          <h5 class="modal-title fw-bold">{{ __('Editar regla') }}</h5>
          <p class="text-muted small mb-0">{{ __('Modifica los parámetros de esta regla') }}</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body pt-3">

        {{-- Sección 1: Nombre --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">1</span>
            <span class="fw-semibold">{{ __('Nombre de la regla') }}</span>
          </div>
          <input type="text" name="name" id="edit-name" class="form-control"
                 placeholder="{{ __('Ej: Alta demanda servidor principal') }}">
        </div>

        <hr class="my-0 mb-4">

        {{-- Sección 2: Condición --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">2</span>
            <span class="fw-semibold">{{ __('Condición de disparo') }}</span>
          </div>
          <div class="d-flex align-items-center flex-wrap gap-2 p-3 rounded mb-3"
               style="background:var(--background-color);border:1px dashed var(--border-color);">
            <span class="small text-muted">{{ __('Si') }}</span>
            <span class="badge bg-secondary">{{ __('valor del dispositivo') }}</span>
            <select name="operator" id="edit-operator" class="form-select form-select-sm w-auto" style="min-width:5rem;">
              @foreach (['>' => '> (mayor)', '>=' => '≥ (mayor o igual)', '<' => '< (menor)', '<=' => '≤ (menor o igual)', '==' => '= (igual)', '!=' => '≠ (distinto)'] as $op => $label)
                <option value="{{ $op }}">{{ $label }}</option>
              @endforeach
            </select>
            <div class="input-group input-group-sm" style="width:9rem;">
              <input type="number" name="value" id="edit-value" step="0.01" min="0" class="form-control" placeholder="0.00">
              <span class="input-group-text">kWh</span>
            </div>
            <span class="small text-muted">{{ __('durante') }}</span>
            <div class="input-group input-group-sm" style="width:8rem;">
              <input type="number" name="for_duration" id="edit-for-duration" min="0" step="1" class="form-control" placeholder="0">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>{{ __('Ventana 0 min = alerta instantánea en cuanto se detecte el valor.') }}
          </small>
        </div>

        <hr class="my-0 mb-4">

        {{-- Sección 3: Dispositivos --}}
        <div class="mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">3</span>
            <span class="fw-semibold">{{ __('Dispositivos monitorizados') }}</span>
          </div>
          @if($dispositivos->isEmpty())
            <p class="text-muted small">{{ __('No tienes dispositivos habilitados.') }}</p>
          @else
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto;">
              @foreach ($dispositivos as $d)
                <div class="form-check form-check-hoverable py-1 px-3 rounded">
                  <input class="form-check-input" type="checkbox"
                         name="devices[]"
                         value="{{ $d->id }}"
                         id="edit-dev-{{ $d->id }}">
                  <label class="form-check-label w-100 d-flex align-items-center gap-2"
                         for="edit-dev-{{ $d->id }}" style="cursor:pointer;">
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

        {{-- Sección 4: Canales --}}
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:1.6rem;height:1.6rem;font-size:.75rem;">4</span>
            <span class="fw-semibold">{{ __('Canales de notificación') }}</span>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-3">
            <div>
              <input type="checkbox" class="btn-check" id="edit-ch-telegram" name="methods[]" value="telegram" autocomplete="off">
              <label class="btn btn-outline-info d-flex align-items-center gap-2 px-3" for="edit-ch-telegram">
                <i class="fab fa-telegram"></i> Telegram
              </label>
            </div>
            <div>
              <input type="checkbox" class="btn-check" id="edit-ch-email" name="methods[]" value="email" autocomplete="off">
              <label class="btn btn-outline-warning d-flex align-items-center gap-2 px-3" for="edit-ch-email">
                <i class="fas fa-envelope"></i> {{ __('Correo') }}
              </label>
            </div>
            <div>
              <input type="checkbox" class="btn-check" id="edit-ch-discord" name="methods[]" value="discord" autocomplete="off">
              <label class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3" for="edit-ch-discord">
                <i class="fab fa-discord"></i> Discord
              </label>
            </div>
          </div>

          <div id="edit-tpl-telegram" style="display:none">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-info)!important;border-left-width:3px!important;">
              <label class="small fw-semibold text-info mb-2 d-flex align-items-center gap-1">
                <i class="fab fa-telegram"></i> {{ __('Mensaje Telegram') }}
                <span class="text-muted fw-normal">({{ __('opcional, si vacío usa texto automático') }})</span>
              </label>
              <textarea name="template_telegram" id="edit-template-telegram" class="form-control form-control-sm" rows="2"
                        placeholder="{{ __('Ej: 🚨 Atención: consumo elevado en {dispositivo}') }}"></textarea>
            </div>
          </div>

          <div id="edit-tpl-email" style="display:none">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-warning)!important;border-left-width:3px!important;">
              <label class="small fw-semibold text-warning mb-2 d-flex align-items-center gap-1">
                <i class="fas fa-envelope"></i> {{ __('Mensaje Correo') }}
                <span class="text-muted fw-normal">({{ __('opcional') }})</span>
              </label>
              <textarea name="template_email" id="edit-template-email" class="form-control form-control-sm mb-2" rows="2"
                        placeholder="{{ __('Cuerpo del correo...') }}"></textarea>
              <input type="email" name="recipient_email" id="edit-recipient-email" class="form-control form-control-sm"
                     placeholder="{{ __('Destinatario: correo@ejemplo.com') }}">
            </div>
          </div>

          <div id="edit-tpl-discord" style="display:none">
            <div class="border rounded p-3 mb-2" style="border-color:var(--bs-secondary)!important;border-left-width:3px!important;">
              <label class="small fw-semibold text-secondary mb-2 d-flex align-items-center gap-1">
                <i class="fab fa-discord"></i> {{ __('Mensaje Discord') }}
                <span class="text-muted fw-normal">({{ __('opcional') }})</span>
              </label>
              <textarea name="template_discord" id="edit-template-discord" class="form-control form-control-sm" rows="2"
                        placeholder="{{ __('Mensaje para el webhook de Discord...') }}"></textarea>
            </div>
          </div>
        </div>

      </div>

      <div class="modal-footer border-0 pt-0 justify-content-between">
        <button type="button" id="edit-btn-delete"
                class="btn btn-outline-danger btn-sm">
          <i class="fas fa-trash-alt me-1"></i>{{ __('Eliminar') }}
        </button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
          <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
            <i class="fas fa-save"></i> {{ __('Guardar') }}
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- ── Modal Confirmar Borrado ───────────────────────────────────────────────── --}}
<div class="modal fade" id="modal-rule-delete" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">{{ __('Eliminar regla') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p class="mb-1">{{ __('¿Eliminar') }} <strong id="delete-rule-name"></strong>?</p>
        <p class="text-muted small mb-0">{{ __('Esta acción no se puede deshacer.') }}</p>
      </div>
      <form id="form-rule-delete" method="POST">
        @csrf
        @method('DELETE')
        <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
          <button type="submit" class="btn btn-danger btn-sm">{{ __('Eliminar') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
