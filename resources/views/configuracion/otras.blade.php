@extends('layouts.plantilla')

@section('title', __('Otros parámetros de configuración'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Otros parámetros de configuración') }}</h2>
    </div>

    <div>
        <div class="mb-4">
            <h6 class="text-dark">{{ __('Autorización') }}</h6>
            <div class="table-responsive">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td>{{ __('Intentos de acceso') }}</td>
                            <td><input type="text" class="form-control" placeholder="Número de intentos"></td>
                        </tr>
                        <tr>
                            <td>{{ __('Intervalo de bloqueo de inicio de sesión') }}</td>
                            <td><input type="text" class="form-control" placeholder="Ej: 30 minutos"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="text-dark">{{ __('Seguridad') }}</h6>
            <div class="table-responsive">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td>Pensar que poner</td>
                            <td><input type="text" class="form-control" placeholder="Número de intentos"></td>
                        </tr>
                        <tr>
                            <td>{{ __('Intervalo de bloqueo de inicio de sesión') }}</td>
                            <td><input type="text" class="form-control" placeholder="Ej: 30 minutos"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-start gap-3">
            <button class="btn btn-primary btn-sm" type="button">{{ __('Actualizar') }}</button>
            <button class="btn btn-secondary btn-sm" type="button">{{ __('Reestablecer predeterminados') }}</button>
        </div>
    </div>
@endsection
