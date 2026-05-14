@props([
    'isEdit' => false,
    'rule' => null,
    'devicesList',
])

@php
    $action   = $isEdit ? route('reglas.update', $rule['id']) : route('reglas.guardar');
    $method   = $isEdit ? 'PUT' : 'POST';
    $title    = $isEdit ? __('Editar Regla') : __('Crear Regla');
    $modalId  = $isEdit ? "modal-rule-{$rule['id']}" : 'modal-rule-create';
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    {{-- FORMULARIO PRINCIPAL --}}
    <form method="POST" action="{{ $action }}" class="modal-content">
      @csrf
      @if($isEdit)
        @method('PUT')
      @endif

      <div class="modal-header">
        <h5 class="modal-title" id="{{ $modalId }}-label">{{ $title }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        {{-- Nombre --}}
        <div class="mb-3">
          <label class="form-label">{{ __('Nombre de la regla') }}</label>
          <input type="text" name="name" class="form-control"
                 value="{{ old('name', $rule['name'] ?? '') }}">
        </div>

        {{-- Dispositivos --}}
        <div class="mb-3">
          <label class="form-label">{{ __('Dispositivos / Grupos') }}</label>
          <select name="devices[]" class="form-select" multiple size="6">
            @foreach ($devicesList as $d)
              <option value="{{ $d->id }}"
                {{ in_array($d->id, old('devices', $rule['devices'] ?? [])) ? 'selected' : '' }}>
                {{ $d->nombre }}
              </option>
            @endforeach
          </select>
          <small class="text-muted">{{ __('Ctrl/Cmd para múltiples.') }}</small>
        </div>

        {{-- Operador y valor --}}
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label">{{ __('Operador') }}</label>
            <select name="operator" class="form-select">
              @foreach (['>', '>=', '<', '<=', '==', '!='] as $op)
                <option value="{{ $op }}"
                  {{ old('operator', $rule['operator'] ?? '') === $op ? 'selected' : '' }}>
                  {{ $op }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">{{ __('Valor (kWh)') }}</label>
            <div class="input-group">
              <input type="number" name="value" step="0.01" min="0" class="form-control"
                     value="{{ old('value', $rule['value'] ?? '') }}">
              <span class="input-group-text">kWh</span>
            </div>
          </div>
        </div>

        {{-- Ventana de confirmación --}}
        <div class="mb-3">
          <label class="form-label">
            {{ __('Ventana de confirmación (minutos)') }}
            <small class="text-muted ms-1">{{ __('0 = alerta instantánea') }}</small>
          </label>
          <div class="input-group">
            <input type="number" name="for_duration" class="form-control" min="0" step="1"
                   value="{{ old('for_duration', $rule['for_duration'] ?? 0) }}">
            <span class="input-group-text">min</span>
          </div>
          <small class="text-muted">{{ __('La condición debe cumplirse durante este tiempo antes de enviar la alerta.') }}</small>
        </div>

        {{-- Canales --}}
        <div class="mb-3">
          <label class="form-label">{{ __('Canales de notificación') }}</label><br>
          @foreach (['telegram','email','discord'] as $m)
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="methods[]" value="{{ $m }}"
                     id="{{ $modalId }}-method-{{ $m }}"
                     {{ in_array($m, old('methods', array_keys($rule['methods'] ?? []))) && ($rule['methods'][$m] ?? false) ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $modalId }}-method-{{ $m }}">
                {{ ucfirst($m) }}
              </label>
            </div>
          @endforeach
        </div>

        {{-- Plantillas con Tabs --}}
        <div class="mb-3">
          <label class="form-label">{{ __('Plantillas de mensaje') }}</label>

          <ul class="nav nav-tabs mb-2" id="{{ $modalId }}-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active"
                      id="{{ $modalId }}-telegram-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#{{ $modalId }}-telegram-pane"
                      type="button" role="tab">
                Telegram
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="{{ $modalId }}-email-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#{{ $modalId }}-email-pane"
                      type="button" role="tab">
                {{ __('Correo') }}
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link"
                      id="{{ $modalId }}-discord-tab"
                      data-bs-toggle="tab"
                      data-bs-target="#{{ $modalId }}-discord-pane"
                      type="button" role="tab">
                Discord
              </button>
            </li>
          </ul>

          <div class="tab-content" id="{{ $modalId }}-tab-content">
            {{-- Telegram --}}
            <div class="tab-pane fade show active"
                 id="{{ $modalId }}-telegram-pane"
                 role="tabpanel">
              <textarea name="template_telegram"
                        class="form-control"
                        rows="3"
                        placeholder="{{ __('Plantilla para Telegram') }}">{{ old('template_telegram', $rule['templates']['telegram'] ?? '') }}</textarea>
            </div>

            {{-- Email --}}
            <div class="tab-pane fade"
                 id="{{ $modalId }}-email-pane"
                 role="tabpanel">
              <textarea name="template_email"
                        class="form-control mb-2"
                        rows="3"
                        placeholder="{{ __('Plantilla para Correo') }}">{{ old('template_email', $rule['templates']['email'] ?? '') }}</textarea>
              <input type="email"
                     name="recipient_email"
                     class="form-control"
                     placeholder="{{ __('Dirección de correo') }}"
                     value="{{ old('recipient_email', $rule['recipient_email'] ?? '') }}">
            </div>

            {{-- Discord --}}
            <div class="tab-pane fade"
                 id="{{ $modalId }}-discord-pane"
                 role="tabpanel">
              <textarea name="template_discord"
                        class="form-control"
                        rows="3"
                        placeholder="{{ __('Plantilla para Discord') }}">{{ old('template_discord', $rule['templates']['discord'] ?? '') }}</textarea>
            </div>
          </div>
        </div>

        {{-- Activo --}}
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" name="active"
                 {{ old('active', $rule['active'] ?? false) ? 'checked' : '' }}>
          <label class="form-check-label">{{ __('Regla activa') }}</label>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
        @if ($isEdit)
          <button type="button" class="btn btn-danger"
                  onclick="if(confirm('{{ __('¿Eliminar esta regla?') }}')) { document.getElementById('delete-rule-{{ $rule['id'] }}').submit(); }">
            {{ __('Eliminar') }}
          </button>
        @endif
      </div>
    </form>

    @if ($isEdit)
      {{-- Eliminar --}}
      <form id="delete-rule-{{ $rule['id'] }}" method="POST"
            action="{{ route('reglas.destroy', $rule['id']) }}">
        @csrf
        @method('DELETE')
      </form>
    @endif
  </div>
</div>
