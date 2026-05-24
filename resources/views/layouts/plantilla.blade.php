<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="{{ Auth::user()->tema ?? 'light' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TERSIME')</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('assets/fonts/fontawesome-all.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/css/main.css') }}?v={{ filemtime(public_path('assets/css/main.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/layouts/sidebar.css') }}?v={{ filemtime(public_path('assets/css/layouts/sidebar.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/layouts/navgar.css') }}?v={{ filemtime(public_path('assets/css/layouts/navgar.css')) }}">
    @stack('styles')
</head>

<body>
    @include('layouts._partials.barraSuperior')
    @include('layouts._partials.menuLateral')
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="page-content">
        @yield('contenido')
    </main>
    <script src="{{ asset('assets/bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}?v={{ filemtime(public_path('assets/js/theme.js')) }}"></script>
    @stack('scripts')
</body>

</html>
