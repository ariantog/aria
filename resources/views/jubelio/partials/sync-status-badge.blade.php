@php
    $status = $status ?? 0;
    $errorType = $errorType ?? null;
    $executeBy = $executeBy ?? null;
@endphp
@if($status == 2 && $errorType == 10)
    <span class="inline-flex max-w-full items-center gap-1 rounded-full border border-green-500/20 bg-green-50 px-1.5 py-0.5 text-[10px] font-bold text-green-600 uppercase">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Success {{ $executeBy ? "($executeBy)" : '' }}
    </span>
@elseif($status == 2 && $errorType == 2)
    <span class="inline-flex max-w-full items-center gap-1 rounded-full border border-yellow-500/20 bg-yellow-50 px-1.5 py-0.5 text-[10px] font-bold text-yellow-600 uppercase">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        Duplicate
    </span>
@elseif($status == 1 && $errorType == 1)
    <span class="inline-flex max-w-full items-center gap-1 rounded-full border border-red-500/20 bg-red-50 px-1.5 py-0.5 text-[10px] font-bold text-red-600 uppercase">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Error SKU
    </span>
@elseif($status == 0)
    <span class="inline-flex max-w-full items-center gap-1 rounded-full border border-blue-500/20 bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-600 uppercase">
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-blue-500"></span>
        Pending
    </span>
@else
    <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-[10px] text-gray-600">Status {{ $status }}:{{ $errorType }}</span>
@endif
