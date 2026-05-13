@extends('layouts.plantilla')

@section('title', __('Grupos de usuarios'))

@section('contenido')
    <div class="container-fluid px-2">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">{{ __('Grupos de usuarios') }}</h2>
        </div>

        <div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('Miembros') }}</th>
                            <th>{{ __('Estado') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Administradores</td>
                            <td>Admin</td>
                            <td class="text-success">{{ __('Activado') }}</td>
                        </tr>
                        <tr>
                            <td>API locales</td>
                            <td>Admin</td>
                            <td class="text-success">{{ __('Activado') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button
                class="btn btn-primary btn-sm mt-3"
                type="button"
                data-bs-target="#create-group-modal"
                data-bs-toggle="modal">
                {{ __('Crear grupo de usuarios') }}
            </button>

            <!-- Modal para crear grupo de usuarios -->
            <div class="modal fade" role="dialog" tabindex="-1" id="create-group-modal">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">{{ __('Creación de grupos') }}</h4>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form>
                                <div class="mb-3">
                                    <label for="group-name" class="form-label">{{ __('Nombre del grupo') }}</label>
                                    <input type="text" class="form-control" id="group-name" placeholder="{{ __('Ingrese el nombre del grupo') }}">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Usuarios seleccionados') }}</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <ul id="selected-users-list"
                                            class="list-unstyled border rounded p-2 mb-0 flex-grow-1"
                                            style="max-height: 100px; overflow-y: auto; background: #f9f9f9;">
                                        </ul>
                                        <button class="btn btn-secondary flex-shrink-0" type="button"
                                                onclick="openSelectUsersModal()">
                                            {{ __('Seleccionar') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('Grupos de dispositivos') }}</label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <ul id="selected-devices-list"
                                            class="list-unstyled border rounded p-2 mb-0 flex-grow-1"
                                            style="max-height: 100px; overflow-y: auto; background: #f9f9f9;">
                                        </ul>
                                        <button class="btn btn-secondary flex-shrink-0" type="button"
                                                onclick="openSelectDevicesModal()">
                                            {{ __('Seleccionar') }}
                                        </button>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="web-access" class="form-label">{{ __('Acceso a la interfaz web') }}</label>
                                    <select class="form-select" id="web-access">
                                        <option value="">{{ __('Ninguno') }}</option>
                                        <option value="1">{{ __('Permitido') }}</option>
                                        <option value="2">{{ __('Restringido') }}</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-light" type="button" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                            <button class="btn btn-primary" type="button">{{ __('Crear grupo') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para seleccionar usuarios -->
            <div class="modal fade" role="dialog" tabindex="-1" id="select-users-modal">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">{{ __('Seleccionar usuarios') }}</h4>
                            <button class="btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Usuario') }}</th>
                                            <th>{{ __('Rol') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="checkbox" class="user-checkbox" value="Admin"> Admin</td>
                                            <td>{{ __('Administrador') }}</td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" class="user-checkbox" value="Admin2"> Admin2</td>
                                            <td>{{ __('Administrador') }}</td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" class="user-checkbox" value="Admin3"> Admin3</td>
                                            <td>{{ __('Administrador') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-light" type="button" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                            <button class="btn btn-primary" type="button" onClick="selectUsersAndClose()">{{ __('Seleccionar') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para seleccionar grupos de dispositivos -->
            <div class="modal fade" role="dialog" tabindex="-1" id="select-devices-modal">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">{{ __('Seleccionar grupos de dispositivos') }}</h4>
                            <button class="btn-close" type="button" aria-label="Close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Nombre de grupo') }}</th>
                                            <th>{{ __('Descripción') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="checkbox" class="devices-checkbox" value="P1"> P1</td>
                                            <td>{{ __('Planta superior') }}</td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" class="devices-checkbox" value="PB"> PB</td>
                                            <td>{{ __('Planta baja') }}</td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" class="devices-checkbox" value="Exterior"> Exterior</td>
                                            <td>{{ __('Exterior') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-light" type="button" data-bs-dismiss="modal">{{ __('Cerrar') }}</button>
                            <button class="btn btn-primary" type="button" onClick="selectDevicesAndClose()">{{ __('Seleccionar') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
function openSelectUsersModal() {
    bootstrap.Modal.getInstance(document.getElementById('create-group-modal')).hide();
    new bootstrap.Modal(document.getElementById('select-users-modal')).show();
}

function selectUsersAndClose() {
    const selected = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(el => el.value);
    document.getElementById('selected-users-list').innerHTML = selected
        .map(u => `<li class="rounded border px-2 py-1 mb-1 small bg-light">${u}</li>`)
        .join('');
    bootstrap.Modal.getInstance(document.getElementById('select-users-modal')).hide();
    new bootstrap.Modal(document.getElementById('create-group-modal')).show();
}

function openSelectDevicesModal() {
    bootstrap.Modal.getInstance(document.getElementById('create-group-modal')).hide();
    new bootstrap.Modal(document.getElementById('select-devices-modal')).show();
}

function selectDevicesAndClose() {
    const selected = Array.from(document.querySelectorAll('.devices-checkbox:checked')).map(el => el.value);
    document.getElementById('selected-devices-list').innerHTML = selected
        .map(d => `<li class="rounded border px-2 py-1 mb-1 small bg-light">${d}</li>`)
        .join('');
    bootstrap.Modal.getInstance(document.getElementById('select-devices-modal')).hide();
    new bootstrap.Modal(document.getElementById('create-group-modal')).show();
}
</script>
@endpush

@endsection
