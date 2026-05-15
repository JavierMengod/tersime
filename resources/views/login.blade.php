<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Login | TERSIME</title>
    <script>if(window.matchMedia('(prefers-color-scheme: dark)').matches){document.documentElement.setAttribute('data-bs-theme','dark');}</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome-all.min.css') }}">

    <link rel="stylesheet" href="{{ asset('assets/css/pages/login.css') }}">
</head>

<body class="login-page">

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-sm-10 col-md-8 col-lg-4">

                <div class="card login-card">
                    <header class="login-header">
                        <img src="{{ asset('assets/img/TERSIME.png') }}" alt="Logo TERSIME">
                        <h1 class="h3 fw-bold">{{ __('¡Bienvenido!') }}</h1>
                        <p class="mb-0">{{ __('Inicia sesión para continuar') }}</p>
                    </header>

                    <section class="login-body">
                        <div class="user-icon-wrapper">
                            <div class="user-icon-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
                                    <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664z"/>
                                </svg>
                            </div>
                        </div>

                        <form action="{{ route('login.store') }}" method="POST" class="login-form">
                            @csrf

                            <div class="mb-3">
                                <label for="user" class="form-label d-none">{{ __('Usuario') }}</label>
                                <input type="text" id="user" name="user" class="form-control" placeholder="{{ __('Usuario') }}" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label d-none">{{ __('Contraseña') }}</label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="{{ __('Contraseña') }}" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                                {{ __('Iniciar sesión') }}
                            </button>

                            @if($errors->any())
                                <div class="alert alert-danger py-2 shadow-sm">
                                    <p class="text-center small mb-0">{{ __('Credenciales incorrectas') }}</p>
                                </div>
                            @endif

                            <div class="text-center">
                                <a href="#" class="small-link">{{ __('¿Olvidaste tu contraseña?') }}</a>
                            </div>
                        </form>
                    </section>
                </div>

            </div>
        </div>
    </main>

    <script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
</body>
</html>
