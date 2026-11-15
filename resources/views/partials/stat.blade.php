<div class="card stat-card h-100">
    <div class="card-body">
        <div class="label">{{ $label }}</div>
        <div class="value {{ $class ?? '' }}">{{ $value }}</div>
        @isset($hint)<div class="hint">{!! $hint !!}</div>@endisset
    </div>
</div>
