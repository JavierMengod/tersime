@php
$isMonitoring = request()->routeIs('monitorizacion.*') || request()->routeIs('prediccion.*');
$isAlertas    = request()->routeIs('alertas.*');
$isInformes   = request()->routeIs('informes.*') || request()->routeIs('programaciones.*');
$isUsuarios   = request()->routeIs('usuarios.*') || request()->routeIs('tokens.*') || request()->routeIs('notificaciones.*');
$isConfig     = request()->routeIs('configuracion.*');
@endphp

<nav class="sidebar" id="barra-lateral">

    <div class="sidebar__container container-fluid d-flex flex-column p-0">

        <div class="sidebar__mobile-header d-flex d-md-none align-items-center justify-content-between px-3 py-2">
            <span class="sidebar__menu-label text-white fw-semibold">{{ __('Menú') }}</span>
            <button type="button" class="sidebar__close-btn" id="sidebarClose" aria-label="{{ __('Cerrar menú') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
                </svg>
            </button>
        </div>

        <hr class="sidebar__divider my-0">

        <ul class="sidebar__menu" id="sidebarMenu">

            {{-- Dashboard --}}
            <li class="sidebar__item">
                <a class="sidebar__link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   href="{{ route('dashboard') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor"
                        viewBox="0 0 16 16" class="sidebar__icon bi bi-grid-fill">
                        <path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5z"/>
                    </svg>
                    <span class="sidebar__text">{{ __('Dashboard') }}</span>
                </a>
            </li>

            {{-- Monitorización --}}
            <li class="sidebar__item">
                <a class="sidebar__link sidebar__link--toggle {{ $isMonitoring ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#sidebarMonitorizacion"
                   aria-expanded="{{ $isMonitoring ? 'true' : 'false' }}"
                   aria-controls="sidebarMonitorizacion">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="sidebar__icon icon icon-tabler icon-tabler-device-desktop-analytics">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                        <path d="M3 4m0 1a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1z"></path>
                        <path d="M7 20h10"></path>
                        <path d="M9 16v4"></path>
                        <path d="M15 16v4"></path>
                        <path d="M9 12v-4"></path>
                        <path d="M12 12v-1"></path>
                        <path d="M15 12v-2"></path>
                        <path d="M12 12v-1"></path>
                    </svg>
                    <span class="sidebar__text">{{ __('Monitorización') }}</span>
                </a>
                <div class="collapse sidebar__submenu {{ $isMonitoring ? 'show' : '' }}" id="sidebarMonitorizacion">
                    <a class="sidebar__dropdown-item {{ request()->routeIs('monitorizacion.tiempo-real') ? 'active' : '' }}"
                       href="{{ route('monitorizacion.tiempo-real') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                            stroke-linejoin="round"
                            class="sidebar__dropdown-icon icon icon-tabler icon-tabler-heart-rate-monitor">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                            <path d="M3 4m0 1a1 1 0 0 1 1 -1h16a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-16a1 1 0 0 1 -1 -1z"></path>
                            <path d="M7 20h10"></path>
                            <path d="M9 16v4"></path>
                            <path d="M15 16v4"></path>
                            <path d="M7 10h2l2 3l2 -6l1 3h3"></path>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Tiempo real') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('prediccion.*') ? 'active' : '' }}"
                       href="{{ route('prediccion.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 16 16"
                            class="sidebar__dropdown-icon">
                            <path d="M3 3 V13" fill="none" stroke="currentColor" stroke-width="0.9" stroke-linecap="round"/>
                            <path d="M3 13 H14" fill="none" stroke="currentColor" stroke-width="0.9" stroke-linecap="round"/>
                            <polyline points="3.5,11 6,7.5 9,9.5 12,5 14,7" fill="none" stroke="currentColor"
                                stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="3.5" cy="11" r="0.55" fill="currentColor"/>
                            <circle cx="6" cy="7.5" r="0.55" fill="currentColor"/>
                            <circle cx="9" cy="9.5" r="0.55" fill="currentColor"/>
                            <circle cx="12" cy="5" r="0.55" fill="currentColor"/>
                            <circle cx="14" cy="7" r="0.55" fill="currentColor"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Predicción de consumo') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('monitorizacion.dispositivos') || request()->routeIs('dispositivos.*') ? 'active' : '' }}"
                       href="{{ route('monitorizacion.dispositivos') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor"
                            viewBox="0 0 16 16" class="sidebar__dropdown-icon bi bi-device-ssd-fill">
                            <path d="M5 8V4h6v4z"></path>
                            <path d="M4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zm0 1.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0m9 0a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0M3.5 11a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1m9.5-.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0M4.75 3h6.5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-.75.75h-6.5A.75.75 0 0 1 4 8.25v-4.5A.75.75 0 0 1 4.75 3M5 12h6a1 1 0 0 1 1 1v2h-1v-2h-.75v2h-1v-2H8.5v2h-1v-2h-.75v2h-1v-2H5v2H4v-2a1 1 0 0 1 1-1"></path>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Dispositivos') }}</span>
                    </a>
                </div>
            </li>

            {{-- Alertas --}}
            <li class="sidebar__item">
                <a class="sidebar__link sidebar__link--toggle {{ $isAlertas ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#sidebarAlertas"
                   aria-expanded="{{ $isAlertas ? 'true' : 'false' }}"
                   aria-controls="sidebarAlertas">
                    <i class="sidebar__icon far fa-bell"></i>
                    <span class="sidebar__text">{{ __('Alertas') }}</span>
                </a>
                <div class="collapse sidebar__submenu {{ $isAlertas ? 'show' : '' }}" id="sidebarAlertas">
                    <a class="sidebar__dropdown-item {{ request()->routeIs('alertas.acciones') ? 'active' : '' }}"
                       href="{{ route('alertas.acciones') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M8 11C10.2091 11 12 9.20914 12 7C12 4.79086 10.2091 3 8 3C5.79086 3 4 4.79086 4 7C4 9.20914 5.79086 11 8 11ZM8 9C9.10457 9 10 8.10457 10 7C10 5.89543 9.10457 5 8 5C6.89543 5 6 5.89543 6 7C6 8.10457 6.89543 9 8 9Z"
                                fill="currentColor"/>
                            <path d="M11 14C11.5523 14 12 14.4477 12 15V21H14V15C14 13.3431 12.6569 12 11 12H5C3.34315 12 2 13.3431 2 15V21H4V15C4 14.4477 4.44772 14 5 14H11Z" fill="currentColor"/>
                            <path d="M22 11H16V13H22V11Z" fill="currentColor"/>
                            <path d="M16 15H22V17H16V15Z" fill="currentColor"/>
                            <path d="M22 7H16V9H22V7Z" fill="currentColor"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Acciones') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('alertas.historial') ? 'active' : '' }}"
                       href="{{ route('alertas.historial') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="currentColor" class="sidebar__dropdown-icon">
                            <path d="M13 3C8.03 3 4 7.03 4 12H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06L6.64 18.36C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5.25l4.5 2.67.75-1.23-3.75-2.22V8H12z"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Historial') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('alertas.medios') ? 'active' : '' }}"
                       href="{{ route('alertas.medios') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7ZM14 7C14 8.10457 13.1046 9 12 9C10.8954 9 10 8.10457 10 7C10 5.89543 10.8954 5 12 5C13.1046 5 14 5.89543 14 7Z"
                                fill="currentColor"/>
                            <path d="M16 15C16 14.4477 15.5523 14 15 14H9C8.44772 14 8 14.4477 8 15V21H6V15C6 13.3431 7.34315 12 9 12H15C16.6569 12 18 13.3431 18 15V21H16V15Z" fill="currentColor"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Métodos de notificación') }}</span>
                    </a>
                </div>
            </li>

            {{-- Informes --}}
            <li class="sidebar__item">
                <a class="sidebar__link sidebar__link--toggle {{ $isInformes ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#sidebarInformes"
                   aria-expanded="{{ $isInformes ? 'true' : 'false' }}"
                   aria-controls="sidebarInformes">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor"
                        viewBox="0 0 16 16" class="sidebar__icon bi bi-clipboard-data-fill">
                        <path d="M6.5 0A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0zm3 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5z"></path>
                        <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1A2.5 2.5 0 0 1 9.5 5h-3A2.5 2.5 0 0 1 4 2.5zM10 8a1 1 0 1 1 2 0v5a1 1 0 1 1-2 0zm-6 4a1 1 0 1 1 2 0v1a1 1 0 1 1-2 0zm4-3a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0v-3a1 1 0 0 1 1-1"></path>
                    </svg>
                    <span class="sidebar__text">{{ __('Informes') }}</span>
                </a>
                <div class="collapse sidebar__submenu {{ $isInformes ? 'show' : '' }}" id="sidebarInformes">
                    <a class="sidebar__dropdown-item {{ request()->routeIs('informes.demanda') ? 'active' : '' }}"
                       href="{{ route('informes.demanda') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path d="M8 13C7.44772 13 7 12.5523 7 12C7 11.4477 7.44772 11 8 11C8.55228 11 9 11.4477 9 12C9 12.5523 8.55228 13 8 13Z" fill="currentColor"></path>
                            <path d="M8 17C7.44772 17 7 16.5523 7 16C7 15.4477 7.44772 15 8 15C8.55228 15 9 15.4477 9 16C9 16.5523 8.55228 17 8 17Z" fill="currentColor"></path>
                            <path d="M11 16C11 16.5523 11.4477 17 12 17C12.5523 17 13 16.5523 13 16C13 15.4477 12.5523 15 12 15C11.4477 15 11 15.4477 11 16Z" fill="currentColor"></path>
                            <path d="M16 17C15.4477 17 15 16.5523 15 16C15 15.4477 15.4477 15 16 15C16.5523 15 17 15.4477 17 16C17 16.5523 16.5523 17 16 17Z" fill="currentColor"></path>
                            <path d="M11 12C11 12.5523 11.4477 13 12 13C12.5523 13 13 12.5523 13 12C13 11.4477 12.5523 11 12 11C11.4477 11 11 11.4477 11 12Z" fill="currentColor"></path>
                            <path d="M16 13C15.4477 13 15 12.5523 15 12C15 11.4477 15.4477 11 16 11C16.5523 11 17 11.4477 17 12C17 12.5523 16.5523 13 16 13Z" fill="currentColor"></path>
                            <path d="M8 7C7.44772 7 7 7.44772 7 8C7 8.55228 7.44772 9 8 9H16C16.5523 9 17 8.55228 17 8C17 7.44772 16.5523 7 16 7H8Z" fill="currentColor"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M6 3C4.34315 3 3 4.34315 3 6V18C3 19.6569 4.34315 21 6 21H18C19.6569 21 21 19.6569 21 18V6C21 4.34315 19.6569 3 18 3H6ZM18 5H6C5.44772 5 5 5.44772 5 6V18C5 18.5523 5.44772 19 6 19H18C18.5523 19 19 18.5523 19 18V6C19 5.44772 18.5523 5 18 5Z" fill="currentColor"></path>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Informes bajo demanda') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('informes.programados') ? 'active' : '' }}"
                       href="{{ route('informes.programados') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12Z" fill="currentColor"/>
                            <path d="M13 7H11V12.4142L14.2929 15.7071L15.7071 14.2929L13 11.5858V7Z" fill="currentColor"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Informes programados') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('informes.registro') || request()->routeIs('informes.registros.*') ? 'active' : '' }}"
                       href="{{ route('informes.registro') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path d="M9 7H15V9H9V7Z" fill="currentColor"/>
                            <path d="M7 11H17V13H7V11Z" fill="currentColor"/>
                            <path d="M7 15H13V17H7V15Z" fill="currentColor"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M5 3C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3H5ZM5 5H19V19H5V5Z" fill="currentColor"/>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Registro de informes') }}</span>
                    </a>
                </div>
            </li>

            {{-- Usuarios --}}
            <li class="sidebar__item">
                <a class="sidebar__link sidebar__link--toggle {{ $isUsuarios ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#sidebarUsuarios"
                   aria-expanded="{{ $isUsuarios ? 'true' : 'false' }}"
                   aria-controls="sidebarUsuarios">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 20 20"
                        fill="none" class="sidebar__icon">
                        <path d="M9 6C9 7.65685 7.65685 9 6 9C4.34315 9 3 7.65685 3 6C3 4.34315 4.34315 3 6 3C7.65685 3 9 4.34315 9 6Z" fill="currentColor"></path>
                        <path d="M17 6C17 7.65685 15.6569 9 14 9C12.3431 9 11 7.65685 11 6C11 4.34315 12.3431 3 14 3C15.6569 3 17 4.34315 17 6Z" fill="currentColor"></path>
                        <path d="M12.9291 17C12.9758 16.6734 13 16.3395 13 16C13 14.3648 12.4393 12.8606 11.4998 11.6691C12.2352 11.2435 13.0892 11 14 11C16.7614 11 19 13.2386 19 16V17H12.9291Z" fill="currentColor"></path>
                        <path d="M6 11C8.76142 11 11 13.2386 11 16V17H1V16C1 13.2386 3.23858 11 6 11Z" fill="currentColor"></path>
                    </svg>
                    <span class="sidebar__text">{{ __('Usuarios') }}</span>
                </a>
                <div class="collapse sidebar__submenu {{ $isUsuarios ? 'show' : '' }}" id="sidebarUsuarios">
                    <a class="sidebar__dropdown-item {{ request()->routeIs('usuarios.index') ? 'active' : '' }}"
                       href="{{ route('usuarios.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"
                            fill="none" class="sidebar__dropdown-icon">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7ZM14 7C14 8.10457 13.1046 9 12 9C10.8954 9 10 8.10457 10 7C10 5.89543 10.8954 5 12 5C13.1046 5 14 5.89543 14 7Z"
                                fill="currentColor"></path>
                            <path d="M16 15C16 14.4477 15.5523 14 15 14H9C8.44772 14 8 14.4477 8 15V21H6V15C6 13.3431 7.34315 12 9 12H15C16.6569 12 18 13.3431 18 15V21H16V15Z" fill="currentColor"></path>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Usuarios') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('tokens.*') ? 'active' : '' }}"
                       href="{{ route('tokens.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor"
                            viewBox="0 0 16 16" class="sidebar__dropdown-icon bi bi-key-fill">
                            <path d="M3.5 11.5a3.5 3.5 0 1 1 3.163-5H14L15.5 8 14 9.5l-1-1-1 1-1-1-1 1-1-1-1 1H6.663a3.5 3.5 0 0 1-3.163 2zM2.5 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2"></path>
                        </svg>
                        <span class="sidebar__dropdown-text">{{ __('Tokens de API') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('notificaciones.*') ? 'active' : '' }}"
                       href="{{ route('notificaciones.index') }}">
                        <i class="sidebar__dropdown-icon bi bi-bell-fill"></i>
                        <span class="sidebar__dropdown-text">{{ __('Notificaciones') }}</span>
                    </a>
                </div>
            </li>

            {{-- Configuración --}}
            <li class="sidebar__item">
                <a class="sidebar__link sidebar__link--toggle {{ $isConfig ? '' : 'collapsed' }}"
                   data-bs-toggle="collapse"
                   href="#sidebarConfiguracion"
                   aria-expanded="{{ $isConfig ? 'true' : 'false' }}"
                   aria-controls="sidebarConfiguracion">
                    <i class="sidebar__icon fas fa-cog"></i>
                    <span class="sidebar__text">{{ __('Configuración') }}</span>
                </a>
                <div class="collapse sidebar__submenu {{ $isConfig ? 'show' : '' }}" id="sidebarConfiguracion">
                    <a class="sidebar__dropdown-item {{ request()->routeIs('configuracion.cuenta') ? 'active' : '' }}"
                       href="{{ route('configuracion.cuenta') }}">
                        <i class="sidebar__dropdown-icon bi bi-person-gear"></i>
                        <span class="sidebar__dropdown-text">{{ __('Mi cuenta') }}</span>
                    </a>
                    @if(auth()->user()->admin)
                    <a class="sidebar__dropdown-item {{ request()->routeIs('configuracion.sistema') ? 'active' : '' }}"
                       href="{{ route('configuracion.sistema') }}">
                        <i class="sidebar__dropdown-icon bi bi-sliders"></i>
                        <span class="sidebar__dropdown-text">{{ __('Sistema') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('configuracion.conexiones') ? 'active' : '' }}"
                       href="{{ route('configuracion.conexiones') }}">
                        <i class="sidebar__dropdown-icon bi bi-plug"></i>
                        <span class="sidebar__dropdown-text">{{ __('Conexiones') }}</span>
                    </a>
                    <a class="sidebar__dropdown-item {{ request()->routeIs('configuracion.logs') ? 'active' : '' }}"
                       href="{{ route('configuracion.logs') }}">
                        <i class="sidebar__dropdown-icon bi bi-journal-text"></i>
                        <span class="sidebar__dropdown-text">{{ __('Logs') }}</span>
                    </a>
                    @endif
                </div>
            </li>

        </ul>

    </div>

</nav>
