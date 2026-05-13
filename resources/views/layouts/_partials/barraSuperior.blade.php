<nav class="navbar navbar-expand shadow topbar topbar--fixed">
    <div class="container-fluid topbar__inner">
        <button
            class="btn btn-link d-md-none rounded-circle me-3 topbar__icon-button"
            id="sidebarToggleTop"
            type="button"
            aria-label="{{ __('Acciones') }}"
        >
            <i class="fas fa-bars"></i>
        </button>

        <div class="topbar__brand">
            <img
                id="logo-imagen"
                class="topbar__logo"
                src="{{ asset('assets/img/TERSIME.png') }}"
                alt="TERSIME"
            >
        </div>

        <div class="topbar__favorites" id="contenedor-favoritos"></div>

        <ul class="navbar-nav flex-nowrap ms-auto topbar__actions">
            <li class="nav-item dropdown no-arrow mx-1 topbar__action">
                <button
                    class="nav-link dropdown-toggle topbar__icon-button"
                    id="languagesDropdown"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="{{ __('Selecciona un lenguaje') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 20 20" fill="none" class="fa-fw topbar__icon-svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M7.00001 2C7.55229 2 8.00001 2.44772 8.00001 3V4H8.73223C8.744 3.99979 8.75581 3.99979 8.76765 4H11C11.5523 4 12 4.44772 12 5C12 5.55228 11.5523 6 11 6H9.57801C9.21635 7.68748 8.63076 9.29154 7.85405 10.7796C8.14482 11.1338 8.44964 11.476 8.76767 11.8055C9.15124 12.2028 9.14007 12.8359 8.74272 13.2195C8.34537 13.603 7.7123 13.5919 7.32873 13.1945C7.13962 12.9986 6.95468 12.7987 6.77405 12.5948C5.88895 13.9101 4.84387 15.1084 3.66692 16.1618C3.2554 16.5301 2.6232 16.4951 2.25487 16.0836C1.88655 15.672 1.92157 15.0398 2.3331 14.6715C3.54619 13.5858 4.60214 12.3288 5.4631 10.9389C4.90663 10.1499 4.40868 9.31652 3.97558 8.44503C3.7298 7.95045 3.93148 7.35027 4.42606 7.10449C4.92064 6.8587 5.52083 7.06039 5.76661 7.55497C6.00021 8.02502 6.25495 8.48278 6.52961 8.92699C6.947 7.99272 7.28247 7.01402 7.52698 6H3.00001C2.44772 6 2.00001 5.55228 2.00001 5C2.00001 4.44772 2.44772 4 3.00001 4H6.00001V3C6.00001 2.44772 6.44772 2 7.00001 2ZM13 8C13.3788 8 13.725 8.214 13.8944 8.55279L16.8854 14.5348C16.8919 14.5471 16.8982 14.5596 16.9041 14.5722L17.8944 16.5528C18.1414 17.0468 17.9412 17.6474 17.4472 17.8944C16.9532 18.1414 16.3526 17.9412 16.1056 17.4472L15.382 16H10.618L9.89444 17.4472C9.64745 17.9412 9.04677 18.1414 8.5528 17.8944C8.05882 17.6474 7.85859 17.0468 8.10558 16.5528L9.09589 14.5722C9.10187 14.5596 9.1081 14.5471 9.11458 14.5348L12.1056 8.55279C12.275 8.214 12.6212 8 13 8ZM11.618 14H14.382L13 11.2361L11.618 14Z" fill="currentColor"></path>
                    </svg>
                </button>

                <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in topbar__dropdown">
                    <h6 class="dropdown-header">{{ __('Selecciona un lenguaje') }}</h6>

                    <form method="POST" action="{{ route('user.update.language') }}">
                        @csrf
                        @method('PUT')

                        <button type="submit" name="language" value="es" class="dropdown-item topbar__dropdown-item">
                            <img src="{{ asset('assets/img/es.svg') }}" width="21" alt="Español">
                            <span>{{ __('Español') }}</span>
                        </button>

                        <button type="submit" name="language" value="en" class="dropdown-item topbar__dropdown-item">
                            <img src="{{ asset('assets/img/us.svg') }}" width="21" alt="English">
                            <span>{{ __('English') }}</span>
                        </button>

                        <button type="submit" name="language" value="fr" class="dropdown-item topbar__dropdown-item">
                            <img src="{{ asset('assets/img/fr.svg') }}" width="21" alt="Français">
                            <span>{{ __('French') }}</span>
                        </button>
                    </form>
                </div>
            </li>

            <li class="nav-item dropdown no-arrow mx-1 topbar__action">
                <button
                    class="nav-link dropdown-toggle topbar__icon-button position-relative"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="{{ __('Alertas') }}"
                >
                    <span class="badge bg-danger badge-counter topbar__badge">1+</span>
                    <i class="fas fa-bell fa-fw"></i>
                </button>

                <div class="dropdown-menu dropdown-menu-end dropdown-list animated--grow-in topbar__dropdown">
                    <h6 class="dropdown-header">{{ __('ALERTAS') }}</h6>

                    <a class="dropdown-item d-flex align-items-center" href="#">
                        <div class="me-3">
                            <div class="bg-primary icon-circle">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                        </div>
                        <div>
                            <span class="small text-gray-500 d-block">Noviembre 11, 2024</span>
                            <p class="mb-0 topbar__dropdown-text">{{ __('Informe automático realizado') }}</p>
                        </div>
                    </a>
                </div>
            </li>

            <li class="nav-item mx-1 topbar__action">
                <button type="button" class="nav-link topbar__icon-button" id="pantalla-completa" aria-label="Pantalla completa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" class="fa-fw topbar__icon-svg">
                        <path fill-rule="evenodd" d="M5.828 10.172a.5.5 0 0 0-.707 0l-4.096 4.096V11.5a.5.5 0 0 0-1 0v3.975a.5.5 0 0 0 .5.5H4.5a.5.5 0 0 0 0-1H1.732l4.096-4.096a.5.5 0 0 0 0-.707zm4.344 0a.5.5 0 0 1 .707 0l4.096 4.096V11.5a.5.5 0 1 1 1 0v3.975a.5.5 0 0 1-.5.5H11.5a.5.5 0 0 1 0-1h2.768l-4.096-4.096a.5.5 0 0 1 0-.707zm0-4.344a.5.5 0 0 0 .707 0l4.096-4.096V4.5a.5.5 0 1 0 1 0V.525a.5.5 0 0 0-.5-.5H11.5a.5.5 0 0 0 0 1h2.768l-4.096 4.096a.5.5 0 0 0 0 .707m-4.344 0a.5.5 0 0 1-.707 0L1.025 1.732V4.5a.5.5 0 0 1-1 0V.525a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 0 1H1.732l4.096 4.096a.5.5 0 0 1 0 .707"></path>
                    </svg>
                </button>

                <button type="button" class="nav-link topbar__icon-button d-none" id="pantalla-completa-exit" aria-label="Salir de pantalla completa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" viewBox="0 0 16 16" class="fa-fw topbar__icon-svg">
                        <path d="M5.5 0a.5.5 0 0 1 .5.5v4A1.5 1.5 0 0 1 4.5 6h-4a.5.5 0 0 1 0-1h4a.5.5 0 0 0 .5-.5v-4a.5.5 0 0 1 .5-.5m5 0a.5.5 0 0 1 .5.5v4a.5.5 0 0 0 .5.5h4a.5.5 0 0 1 0 1h-4A1.5 1.5 0 0 1 10 4.5v-4a.5.5 0 0 1 .5-.5M0 10.5a.5.5 0 0 1 .5-.5h4A1.5 1.5 0 0 1 6 11.5v4a.5.5 0 0 1-1 0v-4a.5.5 0 0 0-.5-.5h-4a.5.5 0 0 1-.5-.5m10 1a1.5 1.5 0 0 1 1.5-1.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 0-.5.5v4a.5.5 0 0 1-1 0z"></path>
                    </svg>
                </button>
            </li>

            <li class="nav-item d-none d-sm-flex align-items-center">
                <div class="topbar__divider"></div>
            </li>

            <li class="nav-item dropdown no-arrow topbar__action">
                <button type="button" class="dropdown-toggle nav-link topbar__user-toggle topbar__icon-button" aria-expanded="false" data-bs-toggle="dropdown">
                    <span class="d-none d-lg-inline me-2 small topbar__user-name">{{ Auth::user()->name }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" class="fa-fw topbar__icon-svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7ZM14 7C14 8.10457 13.1046 9 12 9C10.8954 9 10 8.10457 10 7C10 5.89543 10.8954 5 12 5C13.1046 5 14 5.89543 14 7Z" fill="currentColor"></path>
                        <path d="M16 15C16 14.4477 15.5523 14 15 14H9C8.44772 14 8 14.4477 8 15V21H6V15C6 13.3431 7.34315 12 9 12H15C16.6569 12 18 13.3431 18 15V21H16V15Z" fill="currentColor"></path>
                    </svg>
                </button>

                <div class="dropdown-menu shadow dropdown-menu-end animated--grow-in topbar__dropdown topbar__dropdown--user">
                    <a class="dropdown-item" href="{{ route('perfil') }}">
                        <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                        {{ __('Perfil') }}
                    </a>
                    <a class="dropdown-item" href="{{ route('configuracion-iu') }}">
                        <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                        {{ __('Ajustes') }}
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('logout') }}">
                        <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                        {{ __('Salir') }}
                    </a>
                </div>
            </li>
        </ul>
    </div>
</nav>
