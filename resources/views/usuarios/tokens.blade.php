@extends('layouts.plantilla')

@section('title', __('Tokens de Usuario'))

@section('contenido')
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>{{ __('Tokens de Usuario') }}</h2>
            <form method="POST" action="{{ route('tokens.store') }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="nombre" class="form-control" placeholder="{{ __('Nombre del token') }}" required>
                <button type="submit" class="btn btn-primary">{{ __('Crear Token') }}</button>
            </form>
        </div>

        @if (session('token_creado'))
            <div class="alert alert-success d-flex flex-column gap-2">
                <div>
                    <strong>{{ __('Token generado:') }}</strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <code id="tokenTexto" class="bg-light p-2 rounded user-select-all">
                        {{ session('token_creado') }}
                    </code>
                    <button class="btn btn-sm btn-outline-secondary" id="btnCopiar">
                        <i class="bi bi-clipboard"></i> {{ __('Copiar') }}
                    </button>
                </div>
                <small>⚠️ {{ __('Cópialo ahora, no se mostrará otra vez.') }}</small>
            </div>

            @push('scripts')
            <script>
                const MSG_COPIAR = '{{ __('Copiar') }}';
                const MSG_COPIADO = '{{ __('Copiado') }}';
                document.getElementById('btnCopiar').addEventListener('click', function() {
                    const token = document.getElementById('tokenTexto').innerText;
                    navigator.clipboard.writeText(token).then(() => {
                        this.innerHTML = `<i class="bi bi-clipboard-check"></i> ${MSG_COPIADO}`;
                        this.classList.replace('btn-outline-secondary', 'btn-success');
                        setTimeout(() => {
                            this.innerHTML = `<i class="bi bi-clipboard"></i> ${MSG_COPIAR}`;
                            this.classList.replace('btn-success', 'btn-outline-secondary');
                        }, 2000);
                    });
                });
            </script>
            @endpush
        @endif

        <div class="table-responsive mt-4">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Nombre') }}</th>
                        <th>{{ __('Creado') }}</th>
                        <th>{{ __('Último uso') }}</th>
                        <th>{{ __('Acciones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tokens as $token)
                        <tr>
                            <td>{{ $token->name }}</td>
                            <td>{{ $token->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                @if ($token->last_used_at)
                                    {{ $token->last_used_at->format('Y-m-d H:i') }}
                                @else
                                    <em>{{ __('No usado') }}</em>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('tokens.destroy', $token->id) }}" method="POST"
                                    onsubmit="return confirm('{{ __('¿Eliminar este token?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i> {{ __('Eliminar') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">{{ __('No hay tokens generados aún') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
@endsection
