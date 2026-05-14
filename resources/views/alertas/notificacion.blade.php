@extends('layouts.plantilla')

@section('title', __('Metodos de notificacion'))

@section('contenido')
    <div class="container-fluid px-2">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">{{ __('Métodos de notificación') }}</h2>
        </div>

        <div>
            <div class="row g-4">

                {{-- Telegram --}}
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg"
                                     alt="Telegram"
                                     class="service-logo me-2">
                                <h5 class="mb-0">Telegram</h5>
                            </div>

                            @php $tg = auth()->user()->telegramCredential; @endphp

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @if ($tg && $tg->active)
                                        <span class="badge bg-success">{{ __('Activo') }}</span>
                                    @elseif($tg)
                                        <span class="badge bg-secondary">{{ __('Desactivado') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('No configurado') }}</span>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-sm btn-primary"
                                       data-bs-toggle="modal" data-bs-target="#modal-telegram">
                                        {{ __('Configurar') }}
                                    </a>
                                    @if ($tg)
                                        <form method="POST" action="{{ route('notifications.update', 'telegram') }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="active" value="{{ $tg->active ? 0 : 1 }}">
                                            <button type="submit"
                                                class="btn btn-sm {{ $tg->active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $tg->active ? __('Desactivar') : __('Activar') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Correo --}}
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fas fa-envelope fs-3 me-2 text-secondary"></i>
                                <h5 class="mb-0">{{ __('Correo') }}</h5>
                            </div>

                            @php $smtp = auth()->user()->smtpCredential; @endphp

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @if ($smtp && $smtp->active)
                                        <span class="badge bg-success">{{ __('Activo') }}</span>
                                    @elseif($smtp)
                                        <span class="badge bg-secondary">{{ __('Desactivado') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('No configurado') }}</span>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-sm btn-primary"
                                       data-bs-toggle="modal" data-bs-target="#modal-email">
                                        {{ __('Configurar') }}
                                    </a>
                                    @if ($smtp)
                                        <form method="POST" action="{{ route('notifications.update', 'email') }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="active" value="{{ $smtp->active ? 0 : 1 }}">
                                            <button type="submit"
                                                class="btn btn-sm {{ $smtp->active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $smtp->active ? __('Desactivar') : __('Activar') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Discord --}}
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/7/73/Discord_Color_Text_Logo_%282015-2021%29.svg"
                                     alt="Discord"
                                     class="service-logo me-2">
                                <h5 class="mb-0">Discord</h5>
                            </div>

                            @php $dc = auth()->user()->discordCredential; @endphp

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @if ($dc && $dc->active)
                                        <span class="badge bg-success">{{ __('Activo') }}</span>
                                    @elseif($dc)
                                        <span class="badge bg-secondary">{{ __('Desactivado') }}</span>
                                    @else
                                        <span class="badge bg-warning text-dark">{{ __('No configurado') }}</span>
                                    @endif
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-sm btn-primary"
                                       data-bs-toggle="modal" data-bs-target="#modal-discord">
                                        {{ __('Configurar') }}
                                    </a>
                                    @if ($dc)
                                        <form method="POST" action="{{ route('notifications.update', 'discord') }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="active" value="{{ $dc->active ? 0 : 1 }}">
                                            <button type="submit"
                                                class="btn btn-sm {{ $dc->active ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                {{ $dc->active ? __('Desactivar') : __('Activar') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div> {{-- row --}}
        </div>

    </div> {{-- container-fluid --}}

    {{-- ====== MODALES ====== --}}

        {{-- Modal Telegram --}}
        <div class="modal fade" id="modal-telegram" tabindex="-1" aria-labelledby="modalTelegramLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('notifications.update', 'telegram') }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTelegramLabel">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/8/82/Telegram_logo.svg"
                                 alt="" width="24" class="me-2">
                            {{ __('Configurar Telegram') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @php $tg = auth()->user()->telegramCredential; @endphp
                        <div class="mb-3">
                            <label for="botToken" class="form-label">{{ __('Bot Token') }}</label>
                            <input type="text" class="form-control" id="botToken" name="bot_token"
                                   value="{{ old('bot_token', $tg ? decrypt($tg->bot_token) : '') }}" placeholder="123456:ABC-DEF…">
                        </div>
                        <div class="mb-3">
                            <label for="chatId" class="form-label">{{ __('Chat ID') }}</label>
                            <input type="text" class="form-control" id="chatId" name="chat_id"
                                   value="{{ old('chat_id', $tg->chat_id ?? '') }}" placeholder="-1001234567890">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="telegramActive" name="active" value="1"
                                   {{ old('active', $tg->active ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="telegramActive">{{ __('Activar envío') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Correo --}}
        <div class="modal fade" id="modal-email" tabindex="-1" aria-labelledby="modalEmailLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('notifications.update', 'email') }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalEmailLabel">
                            <i class="fas fa-envelope me-2"></i>
                            {{ __('Configurar Correo') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @php $smtp = auth()->user()->smtpCredential; @endphp
                        <div class="mb-3">
                            <label for="fromAddress" class="form-label">{{ __('From Address') }}</label>
                            <input type="email" class="form-control" id="fromAddress" name="from_address"
                                   value="{{ old('from_address') }}" placeholder="no-reply@dominio.com">
                        </div>
                        <div class="mb-3">
                            <label for="smtpHost" class="form-label">{{ __('SMTP Host') }}</label>
                            <input type="text" class="form-control" id="smtpHost" name="smtp_host"
                                   value="{{ old('smtp_host', $smtp->host ?? '') }}" placeholder="smtp.dominio.com">
                        </div>
                        <div class="mb-3">
                            <label for="smtpPort" class="form-label">{{ __('SMTP Port') }}</label>
                            <input type="number" class="form-control" id="smtpPort" name="smtp_port"
                                   value="{{ old('smtp_port', $smtp->port ?? '') }}" placeholder="587">
                        </div>
                        <div class="mb-3">
                            <label for="smtpUser" class="form-label">{{ __('SMTP User') }}</label>
                            <input type="text" class="form-control" id="smtpUser" name="smtp_user"
                                   value="{{ old('smtp_user', $smtp->username ?? '') }}" placeholder="usuario">
                        </div>
                        <div class="mb-3">
                            <label for="smtpPass" class="form-label">{{ __('SMTP Pass') }}</label>
                            <input type="password" class="form-control" id="smtpPass" name="smtp_pass" value="">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="emailActive" name="active" value="1"
                                   {{ old('active', $smtp->active ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="emailActive">{{ __('Activar envío') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Discord --}}
        <div class="modal fade" id="modal-discord" tabindex="-1" aria-labelledby="modalDiscordLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('notifications.update', 'discord') }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalDiscordLabel">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/7/73/Discord_Color_Text_Logo_%282015-2021%29.svg"
                                 alt="" width="24" class="me-2">
                            {{ __('Configurar Discord') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @php $dc = auth()->user()->discordCredential; @endphp
                        <div class="mb-3">
                            <label for="webhookUrl" class="form-label">{{ __('Webhook URL') }}</label>
                            <input type="url" class="form-control" id="webhookUrl" name="webhook_url"
                                   value="{{ old('webhook_url', $dc->webhook_url ?? '') }}"
                                   placeholder="https://discord.com/api/webhooks/...">
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="discordActive" name="active" value="1"
                                   {{ old('active', $dc->active ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="discordActive">{{ __('Activar envío') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Guardar') }}</button>
                    </div>
                </form>
            </div>
        </div>

@endsection
