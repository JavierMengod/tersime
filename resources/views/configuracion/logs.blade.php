@extends('layouts.plantilla')

@section('title', __('Log del sistema'))

@section('contenido')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0">{{ __('Log del sistema') }}</h2>
        <p class="text-muted mb-0 small">
            storage/logs/laravel.log &middot; {{ $logSize }} KB
            &middot; {{ count($entries) }} {{ __('entradas') }}
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('configuracion.logs.download') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>{{ __('Descargar') }}
        </a>
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modal-vaciar-log">
            <i class="bi bi-trash me-1"></i>{{ __('Vaciar log') }}
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Filtros ──────────────────────────────────────────────────────────────── --}}
<div class="d-flex gap-2 mb-3 flex-wrap" id="log-filters">
    @foreach(['ERROR' => 'danger', 'WARNING' => 'warning', 'INFO' => 'primary', 'DEBUG' => 'secondary'] as $level => $color)
    @php $count = count(array_filter($entries, fn($e) => $e['level'] === $level)) @endphp
    <button class="btn btn-sm btn-outline-{{ $color }} filter-btn {{ $count === 0 ? 'disabled' : '' }}"
            data-level="{{ $level }}">
        {{ $level }} <span class="badge bg-{{ $color }} ms-1">{{ $count }}</span>
    </button>
    @endforeach
    <button class="btn btn-sm btn-outline-secondary filter-btn active" data-level="ALL">
        {{ __('Todos') }} <span class="badge bg-secondary ms-1">{{ count($entries) }}</span>
    </button>
</div>

{{-- Log ─────────────────────────────────────────────────────────────────── --}}
@if(count($entries) === 0)
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
            {{ __('El log está vacío') }}
        </div>
    </div>
@else
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div style="max-height:72vh;overflow-y:auto;" id="log-container">
            <table class="table table-sm align-middle mb-0" style="font-size:.78rem;font-family:monospace;">
                <tbody id="log-tbody">
                @foreach($entries as $entry)
                @php
                    $colorMap = ['ERROR' => 'danger', 'WARNING' => 'warning', 'INFO' => 'primary'];
                    $rowBgMap = ['ERROR' => 'table-danger', 'WARNING' => 'table-warning'];
                    $color = $colorMap[$entry['level']] ?? 'secondary';
                    $rowBg = $rowBgMap[$entry['level']] ?? '';
                @endphp
                <tr class="log-row {{ $rowBg }}" data-level="{{ $entry['level'] }}">
                    <td class="text-nowrap ps-3 text-muted" style="width:145px">{{ $entry['datetime'] }}</td>
                    <td style="width:80px">
                        <span class="badge bg-{{ $color }}">{{ $entry['level'] }}</span>
                    </td>
                    <td class="pe-3" style="word-break:break-word;white-space:pre-wrap;">{{ $entry['message'] }}@if(!empty($entry['extra']))
<span class="text-muted d-block" style="font-size:.75rem;">{{ implode("\n", array_slice($entry['extra'], 0, 3)) }}</span>@endif</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif


{{-- Modal vaciar log --}}
<div class="modal fade" id="modal-vaciar-log" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-trash me-1"></i>{{ __('Vaciar log') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('Se eliminará todo el contenido del archivo de log. Esta acción no se puede deshacer.') }}</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancelar') }}</button>
                <form method="POST" action="{{ route('configuracion.logs.clear') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Vaciar') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var activeLevel = 'ALL';

    document.querySelectorAll('.filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (this.classList.contains('disabled')) return;
            activeLevel = this.dataset.level;

            document.querySelectorAll('.filter-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            this.classList.add('active');

            document.querySelectorAll('.log-row').forEach(function (row) {
                if (activeLevel === 'ALL' || row.dataset.level === activeLevel) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
}());
</script>
@endpush

@endsection
