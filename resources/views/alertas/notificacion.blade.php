@extends('layouts.plantilla')

@section('title', __('Métodos de notificación'))

@section('contenido')
<div class="container-fluid px-2">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">{{ __('Métodos de notificación') }}</h2>
            <p class="text-muted mb-0 small">{{ __('Configura los canales por los que recibirás alertas') }}</p>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">

        {{-- ── TELEGRAM ─────────────────────────────────────────────────────── --}}
        @php $tg = auth()->user()->telegramCredential; @endphp
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid #229ED9 !important;">
                <div class="card-body d-flex flex-column">

                    {{-- Cabecera --}}
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fab fa-telegram fs-3" style="color:#229ED9;"></i>
                            <h5 class="mb-0">Telegram</h5>
                        </div>
                        @if ($tg && $tg->active)
                            <span class="badge bg-success">{{ __('Activo') }}</span>
                        @elseif($tg)
                            <span class="badge bg-secondary">{{ __('Inactivo') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ __('Sin configurar') }}</span>
                        @endif
                    </div>

                    {{-- Resumen configuración --}}
                    <div class="flex-grow-1 mb-3">
                        @if($tg)
                            <div class="small text-muted">
                                <i class="fas fa-hashtag me-1"></i>Chat ID:
                                <code>{{ $tg->chat_id }}</code>
                            </div>
                        @else
                            <p class="small text-muted mb-0">{{ __('Introduce tu Bot Token y Chat ID para recibir alertas vía Telegram.') }}</p>
                        @endif
                    </div>

                    {{-- Acciones --}}
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#modal-telegram">
                            <i class="fas fa-cog me-1"></i>{{ __('Configurar') }}
                        </button>
                        @if ($tg)
                            <form method="POST" action="{{ route('alertas.medios.update', 'telegram') }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="active" value="{{ $tg->active ? 0 : 1 }}">
                                <button type="submit"
                                        class="btn btn-sm {{ $tg->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                        title="{{ $tg->active ? __('Desactivar') : __('Activar') }}">
                                    <i class="fas {{ $tg->active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('alertas.medios.destroy', 'telegram') }}"
                                  onsubmit="return confirm('{{ __('¿Desconectar Telegram? Se borrarán las credenciales guardadas.') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Desconectar') }}">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        {{-- ── CORREO ───────────────────────────────────────────────────────── --}}
        @php $smtp = auth()->user()->smtpCredential; @endphp
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid #f59e0b !important;">
                <div class="card-body d-flex flex-column">

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-envelope fs-3 text-warning"></i>
                            <h5 class="mb-0">{{ __('Correo') }}</h5>
                        </div>
                        @if ($smtp && $smtp->active)
                            <span class="badge bg-success">{{ __('Activo') }}</span>
                        @elseif($smtp)
                            <span class="badge bg-secondary">{{ __('Inactivo') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ __('Sin configurar') }}</span>
                        @endif
                    </div>

                    <div class="flex-grow-1 mb-3">
                        @if($smtp)
                            <div class="small text-muted mb-1">
                                <i class="fas fa-at me-1"></i>{{ $smtp->from_address ?: $smtp->username }}
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-server me-1"></i>{{ $smtp->host }}:{{ $smtp->port }}
                            </div>
                        @else
                            <p class="small text-muted mb-0">{{ __('Configura tu servidor SMTP para enviar alertas por correo electrónico.') }}</p>
                        @endif
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#modal-email">
                            <i class="fas fa-cog me-1"></i>{{ __('Configurar') }}
                        </button>
                        @if ($smtp)
                            <form method="POST" action="{{ route('alertas.medios.update', 'email') }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="active" value="{{ $smtp->active ? 0 : 1 }}">
                                <button type="submit"
                                        class="btn btn-sm {{ $smtp->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                        title="{{ $smtp->active ? __('Desactivar') : __('Activar') }}">
                                    <i class="fas {{ $smtp->active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('alertas.medios.destroy', 'email') }}"
                                  onsubmit="return confirm('{{ __('¿Desconectar el correo? Se borrarán las credenciales SMTP guardadas.') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Desconectar') }}">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        {{-- ── DISCORD ──────────────────────────────────────────────────────── --}}
        @php $dc = auth()->user()->discordCredential; @endphp
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100" style="border-top: 3px solid #5865F2 !important;">
                <div class="card-body d-flex flex-column">

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fab fa-discord fs-3" style="color:#5865F2;"></i>
                            <h5 class="mb-0">Discord</h5>
                        </div>
                        @if ($dc && $dc->active)
                            <span class="badge bg-success">{{ __('Activo') }}</span>
                        @elseif($dc)
                            <span class="badge bg-secondary">{{ __('Inactivo') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ __('Sin configurar') }}</span>
                        @endif
                    </div>

                    <div class="flex-grow-1 mb-3">
                        @if($dc)
                            <div class="small text-muted text-truncate" title="{{ $dc->webhook_url }}">
                                <i class="fas fa-link me-1"></i>{{ $dc->webhook_url }}
                            </div>
                        @else
                            <p class="small text-muted mb-0">{{ __('Añade un webhook de Discord para recibir alertas en tu servidor.') }}</p>
                        @endif
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary flex-grow-1"
                                data-bs-toggle="modal" data-bs-target="#modal-discord">
                            <i class="fas fa-cog me-1"></i>{{ __('Configurar') }}
                        </button>
                        @if ($dc)
                            <form method="POST" action="{{ route('alertas.medios.update', 'discord') }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="active" value="{{ $dc->active ? 0 : 1 }}">
                                <button type="submit"
                                        class="btn btn-sm {{ $dc->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                        title="{{ $dc->active ? __('Desactivar') : __('Activar') }}">
                                    <i class="fas {{ $dc->active ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('alertas.medios.destroy', 'discord') }}"
                                  onsubmit="return confirm('{{ __('¿Desconectar Discord? Se borrará el webhook guardado.') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Desconectar') }}">
                                    <i class="fas fa-unlink"></i>
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>
        </div>

    </div>{{-- /row --}}

</div>{{-- /container-fluid --}}

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- MODALES                                                                    --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}

{{-- Modal Telegram --}}
<div class="modal fade" id="modal-telegram" tabindex="-1" aria-labelledby="modalTelegramLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('alertas.medios.update', 'telegram') }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalTelegramLabel">
                    <i class="fab fa-telegram" style="color:#229ED9;"></i> {{ __('Configurar Telegram') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @php $tg = auth()->user()->telegramCredential; @endphp

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Se enviará un mensaje de prueba al guardar para verificar la conexión.') }}
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">{{ __('Bot Token') }}</label>
                    <input type="password" class="form-control" name="bot_token"
                           placeholder="123456:ABC-DEF…"
                           autocomplete="new-password">
                    @if($tg)
                        <div class="form-text"><i class="fas fa-lock me-1 text-success"></i>{{ __('Token ya guardado. Déjalo vacío para no cambiar.') }}</div>
                    @endif
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">{{ __('Chat ID') }}</label>
                    <input type="text" class="form-control" name="chat_id"
                           value="{{ old('chat_id', $tg->chat_id ?? '') }}"
                           placeholder="-1001234567890">
                    <div class="form-text">{{ __('ID del chat, grupo o canal donde recibirás las alertas.') }}</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ __('Guardar y verificar') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Correo --}}
<div class="modal fade" id="modal-email" tabindex="-1" aria-labelledby="modalEmailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('alertas.medios.update', 'email') }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalEmailLabel">
                    <i class="fas fa-envelope text-warning"></i> {{ __('Configurar Correo SMTP') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @php $smtp = auth()->user()->smtpCredential; @endphp

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Se enviará un correo de prueba a la dirección configurada para verificar el SMTP.') }}
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Dirección de envío (From)') }}</label>
                        <input type="email" class="form-control" name="from_address"
                               value="{{ old('from_address', $smtp->from_address ?? '') }}"
                               placeholder="alertas@tudominio.com">
                    </div>
                    <div class="col-8">
                        <label class="form-label fw-semibold">{{ __('SMTP Host') }}</label>
                        <input type="text" class="form-control" name="smtp_host"
                               value="{{ old('smtp_host', $smtp->host ?? '') }}"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-4">
                        <label class="form-label fw-semibold">{{ __('Puerto') }}</label>
                        <input type="number" class="form-control" name="smtp_port"
                               value="{{ old('smtp_port', $smtp->port ?? 587) }}"
                               placeholder="587">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Usuario SMTP') }}</label>
                        <input type="text" class="form-control" name="smtp_user"
                               value="{{ old('smtp_user', $smtp->username ?? '') }}"
                               placeholder="usuario@dominio.com"
                               autocomplete="username">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">{{ __('Contraseña SMTP') }}</label>
                        <input type="password" class="form-control" name="smtp_pass"
                               placeholder="{{ $smtp ? __('Dejar vacío para no cambiar') : __('Contraseña SMTP') }}"
                               autocomplete="new-password">
                        @if($smtp)
                            <div class="form-text"><i class="fas fa-lock me-1 text-success"></i>{{ __('Contraseña ya guardada.') }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ __('Guardar y verificar') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Discord --}}
<div class="modal fade" id="modal-discord" tabindex="-1" aria-labelledby="modalDiscordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('alertas.medios.update', 'discord') }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalDiscordLabel">
                    <i class="fab fa-discord" style="color:#5865F2;"></i> {{ __('Configurar Discord') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                @php $dc = auth()->user()->discordCredential; @endphp

                <div class="alert alert-info py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    {{ __('Se enviará un mensaje de prueba al webhook para verificar la conexión.') }}
                </div>

                <div class="mb-0">
                    <label class="form-label fw-semibold">{{ __('Webhook URL') }}</label>
                    <input type="url" class="form-control" name="webhook_url"
                           value="{{ old('webhook_url', $dc->webhook_url ?? '') }}"
                           placeholder="https://discord.com/api/webhooks/…">
                    <div class="form-text">{{ __('En Discord: Configuración del canal → Integraciones → Webhooks.') }}</div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>{{ __('Guardar y verificar') }}
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Reabre el modal correcto si hubo un error de validación --}}
@if(session('error_channel'))
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modal-{{ session('error_channel') }}');
    if (el) new bootstrap.Modal(el).show();
});
</script>
@endpush
@endif

@endsection
