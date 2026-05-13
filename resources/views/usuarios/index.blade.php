@extends('layouts.plantilla')

@section('title', __('Gestión de Usuarios'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('Gestión de Usuarios') }}</h2>

        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
            <i class="bi bi-person-plus"></i> {{ __('Nuevo usuario') }}
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Usuario') }}</th>
                    <th>{{ __('Idioma') }}</th>
                    <th>{{ __('Zona horaria') }}</th>
                    <th>{{ __('Tema') }}</th>
                    <th>{{ __('Fecha de creación') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ strtoupper($user->language ?? 'ES') }}</td>
                        <td>{{ $user->timezone ?? 'UTC+01:00' }}</td>
                        <td>
                            <span class="badge bg-{{ $user->theme === 'dark' ? 'dark' : 'light' }}">
                                {{ ucfirst($user->theme ?? 'light') }}
                            </span>
                        </td>
                        <td>{{ $user->created_at ? $user->created_at->format('Y-m-d H:i') : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted">{{ __('No hay usuarios registrados.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-labelledby="modalCrearUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('user.create') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCrearUsuarioLabel">{{ __('Crear nuevo usuario') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="user" class="form-label">{{ __('Nombre de usuario') }}</label>
                        <input type="text" name="user" id="user" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">{{ __('Contraseña') }}</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="language" class="form-label">{{ __('Idioma') }}</label>
                        <select name="language" id="language" class="form-select">
                            <option value="es" selected>{{ __('Español') }}</option>
                            <option value="en">{{ __('Inglés') }}</option>
                            <option value="fr">{{ __('Francés') }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="timezone" class="form-label">{{ __('Zona horaria') }}</label>
                        <input type="text" name="timezone" id="timezone" class="form-control" placeholder="UTC+01:00" value="UTC+01:00">
                    </div>

                    <div class="mb-3">
                        <label for="theme" class="form-label">{{ __('Tema') }}</label>
                        <select name="theme" id="theme" class="form-select">
                            <option value="light" selected>{{ __('Claro') }}</option>
                            <option value="dark">{{ __('Oscuro') }}</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Guardar usuario') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
