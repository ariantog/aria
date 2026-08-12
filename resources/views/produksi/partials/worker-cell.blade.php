@props(['worker', 'type', 'date' => null, 'compact' => false])

@if($worker)
@php
    $boxPad = $compact ? 'p-1' : 'p-1.5';
    $nameMax = $compact ? 'max-w-[72px]' : 'max-w-[100px]';
@endphp
<div class="flex flex-col items-center justify-center rounded-md border border-gray-200 bg-gray-50 {{ $boxPad }}">
    @if($date)
    <span class="mb-0.5 text-[11px] font-medium text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($date)->translatedFormat('d M Y') }}</span>
    @endif
    <a href="{{ route('produksi.'.$type.'.show', $worker->id) }}"
       class="w-full {{ $nameMax }} truncate rounded border border-gray-100 bg-white px-1 py-0.5 text-center text-xs font-bold text-blue-600 shadow-sm hover:bg-blue-50 hover:underline"
       title="{{ $worker->name }}">{{ $worker->name }}</a>
</div>
@else
<span class="font-medium text-gray-400">-</span>
@endif
