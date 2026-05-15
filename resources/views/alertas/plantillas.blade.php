@extends('layouts.plantilla')

@section('title', __('Plantillas de notificación'))

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">{{ __('Plantillas de notificación') }}</h2>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div>
        <ul class="nav nav-tabs mb-3" id="plantillasTabs" role="tablist">
            @foreach (['telegram', 'email', 'discord'] as $index => $canal)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index === 0 ? 'active' : '' }} text-capitalize"
                        id="tab-{{ $canal }}" data-bs-toggle="tab" data-bs-target="#pane-{{ $canal }}"
                        type="button" role="tab" aria-controls="pane-{{ $canal }}"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                        {{ __(ucfirst($canal)) }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content" id="plantillasTabsContent">
            @foreach (['telegram', 'email', 'discord'] as $index => $canal)
                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="pane-{{ $canal }}" role="tabpanel">

                    <h5 class="mb-3">{{ __('Plantillas existentes') }}</h5>
                    @if (isset($plantillas[$canal]) && count($plantillas[$canal]))
                        <ul class="list-group mb-4">
                            @foreach ($plantillas[$canal] as $plantilla)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <pre class="mb-0" style="white-space: pre-wrap;">{{ $plantilla->contenido }}</pre>
                                        <small class="text-muted">{{ __('Creada el') }} {{ $plantilla->created_at->format('d/m/Y H:i') }}</small>
                                    </div>
                                    <form action="{{ route('alertas.plantillas.destroy', [$canal, $plantilla->id]) }}" method="POST" onsubmit="return confirm('{{ __('¿Eliminar esta plantilla?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">{{ __('Eliminar') }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted">{{ __('No hay plantillas registradas.') }}</p>
                    @endif

                    <h5 class="mb-3">{{ __('Añadir nueva plantilla') }}</h5>
                    <form action="{{ route('alertas.plantillas.store', $canal) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="contenido_{{ $canal }}" class="form-label">{{ __('Contenido de la plantilla') }}</label>
                            <textarea id="contenido_{{ $canal }}" name="contenido" class="form-control" rows="5"
                                placeholder="{{ __('Contenido de la plantilla') }}...">{{ old('contenido') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Guardar nueva plantilla') }}</button>
                    </form>

                </div>
            @endforeach
        </div>
    </div>
@endsection
