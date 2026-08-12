@php
    $display = $label ?? $value;
    $isEmpty = $value === null || $value === '' || $display === '-';
    $linkClass = $class ?? 'text-blue-600 hover:underline';
    $query = array_filter(
        array_merge($filters ?? [], request()->query(), [$param => $value]),
        fn ($v) => $v !== null && $v !== '',
    );
@endphp

@if($isEmpty)
<span class="font-medium text-gray-400">-</span>
@else
<a href="{{ route($route, $query) }}" class="{{ $linkClass }}">{{ $display }}</a>
@endif
