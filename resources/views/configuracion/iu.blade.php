@extends('layouts.plantilla')

@section('title', __('Interfaz de usuario'))

@section('contenido')
    <div class="container-fluid px-2">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">{{ __('Interfaz de usuario') }}</h2>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="{{ route('config.update') }}" method="POST">
                    @csrf
                    <div class="row gy-3">

                        <div class="col-12 col-md-6">
                            <label for="language" class="form-label">{{ __('Idioma predeterminado') }}</label>
                            <select name="language" id="language" class="form-select">
                                <option value="es" {{ auth()->user()->language === 'es' ? 'selected' : '' }}>{{ __('Español') }}</option>
                                <option value="en" {{ auth()->user()->language === 'en' ? 'selected' : '' }}>{{ __('Inglés') }}</option>
                                <option value="fr" {{ auth()->user()->language === 'fr' ? 'selected' : '' }}>{{ __('Francés') }}</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="theme" class="form-label">{{ __('Tema por defecto') }}</label>
                            <select name="theme" id="theme" class="form-select">
                                <option value="light" {{ auth()->user()->theme === 'light' ? 'selected' : '' }}>{{ __('Claro') }}</option>
                                <option value="dark" {{ auth()->user()->theme === 'dark' ? 'selected' : '' }}>{{ __('Oscuro') }}</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label for="timezone" class="form-label">{{ __('Zona horaria por defecto') }}</label>
                            <select name="timezone" id="timezone" class="form-select">
                                <option value="UTC-12:00" {{ auth()->user()->timezone === 'UTC-12:00' ? 'selected' : '' }}>Sistema: (UTC-12:00) Baker Island</option>
                                <option value="UTC-11:00" {{ auth()->user()->timezone === 'UTC-11:00' ? 'selected' : '' }}>Sistema: (UTC-11:00) Pago Pago</option>
                                <option value="UTC-10:00" {{ auth()->user()->timezone === 'UTC-10:00' ? 'selected' : '' }}>Sistema: (UTC-10:00) Hawaii-Aleutian</option>
                                <option value="UTC-09:00" {{ auth()->user()->timezone === 'UTC-09:00' ? 'selected' : '' }}>Sistema: (UTC-09:00) Alaska</option>
                                <option value="UTC-08:00" {{ auth()->user()->timezone === 'UTC-08:00' ? 'selected' : '' }}>Sistema: (UTC-08:00) Pacific Time (US &amp; Canada)</option>
                                <option value="UTC-07:00" {{ auth()->user()->timezone === 'UTC-07:00' ? 'selected' : '' }}>Sistema: (UTC-07:00) Mountain Time (US &amp; Canada)</option>
                                <option value="UTC-06:00" {{ auth()->user()->timezone === 'UTC-06:00' ? 'selected' : '' }}>Sistema: (UTC-06:00) Central Time (US &amp; Canada)</option>
                                <option value="UTC-05:00" {{ auth()->user()->timezone === 'UTC-05:00' ? 'selected' : '' }}>Sistema: (UTC-05:00) Eastern Time (US &amp; Canada)</option>
                                <option value="UTC-04:00" {{ auth()->user()->timezone === 'UTC-04:00' ? 'selected' : '' }}>Sistema: (UTC-04:00) Atlantic Time (Canada)</option>
                                <option value="UTC-03:00" {{ auth()->user()->timezone === 'UTC-03:00' ? 'selected' : '' }}>Sistema: (UTC-03:00) Buenos Aires</option>
                                <option value="UTC-02:00" {{ auth()->user()->timezone === 'UTC-02:00' ? 'selected' : '' }}>Sistema: (UTC-02:00) Mid-Atlantic</option>
                                <option value="UTC-01:00" {{ auth()->user()->timezone === 'UTC-01:00' ? 'selected' : '' }}>Sistema: (UTC-01:00) Azores</option>
                                <option value="UTC+00:00" {{ auth()->user()->timezone === 'UTC+00:00' ? 'selected' : '' }}>Sistema: (UTC+00:00) Europe/London</option>
                                <option value="UTC+01:00" {{ auth()->user()->timezone === 'UTC+01:00' ? 'selected' : '' }}>Sistema: (UTC+01:00) Europe/Madrid</option>
                                <option value="UTC+02:00" {{ auth()->user()->timezone === 'UTC+02:00' ? 'selected' : '' }}>Sistema: (UTC+02:00) Europe/Paris</option>
                                <option value="UTC+03:00" {{ auth()->user()->timezone === 'UTC+03:00' ? 'selected' : '' }}>Sistema: (UTC+03:00) Moscow</option>
                                <option value="UTC+04:00" {{ auth()->user()->timezone === 'UTC+04:00' ? 'selected' : '' }}>Sistema: (UTC+04:00) Dubai</option>
                                <option value="UTC+05:00" {{ auth()->user()->timezone === 'UTC+05:00' ? 'selected' : '' }}>Sistema: (UTC+05:00) Pakistan</option>
                                <option value="UTC+06:00" {{ auth()->user()->timezone === 'UTC+06:00' ? 'selected' : '' }}>Sistema: (UTC+06:00) Almaty</option>
                                <option value="UTC+07:00" {{ auth()->user()->timezone === 'UTC+07:00' ? 'selected' : '' }}>Sistema: (UTC+07:00) Bangkok</option>
                                <option value="UTC+08:00" {{ auth()->user()->timezone === 'UTC+08:00' ? 'selected' : '' }}>Sistema: (UTC+08:00) Singapore</option>
                                <option value="UTC+09:00" {{ auth()->user()->timezone === 'UTC+09:00' ? 'selected' : '' }}>Sistema: (UTC+09:00) Tokyo</option>
                                <option value="UTC+10:00" {{ auth()->user()->timezone === 'UTC+10:00' ? 'selected' : '' }}>Sistema: (UTC+10:00) Australia/Sydney</option>
                                <option value="UTC+11:00" {{ auth()->user()->timezone === 'UTC+11:00' ? 'selected' : '' }}>Sistema: (UTC+11:00) Solomon Islands</option>
                                <option value="UTC+12:00" {{ auth()->user()->timezone === 'UTC+12:00' ? 'selected' : '' }}>Sistema: (UTC+12:00) Fiji</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="debug_mode" id="debug_mode"
                                    {{ auth()->user()->debug_mode ? 'checked' : '' }} value="1">
                                <label class="form-check-label" for="debug_mode">{{ __('Modo debug') }}</label>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary btn-sm" type="submit" name="action" value="save">{{ __('Guardar preferencias') }}</button>
                        <button class="btn btn-secondary btn-sm" type="submit" name="action" value="reset">{{ __('Reestablecer predeterminados') }}</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
