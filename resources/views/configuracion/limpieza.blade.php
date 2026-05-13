@extends('layouts.plantilla')

@section('title', __('Limpieza'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Limpieza') }}</h2>
    </div>

    <div>
        <div class="mb-4">
            <h6 class="text-dark">{{ __('Alertas y notificaciones') }}</h6>
            <div class="table-responsive">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td>{{ __('Activar limpieza interna') }}</td>
                            <td>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="limpieza-interna">
                                    <label class="form-check-label" for="limpieza-interna"></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>{{ __('Periodo de almacenamiento de datos') }}</td>
                            <td><input type="text" class="form-control" placeholder="Ej: 30 días"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-4">
            <h6 class="text-dark">{{ __('Logs de actividad') }}</h6>
            <div class="table-responsive">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <td>{{ __('Activar limpieza interna') }}</td>
                            <td>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="logs-limpieza">
                                    <label class="form-check-label" for="logs-limpieza"></label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>{{ __('Periodo de almacenamiento de datos') }}</td>
                            <td><input type="text" class="form-control" placeholder="Ej: 365d"></td>
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
