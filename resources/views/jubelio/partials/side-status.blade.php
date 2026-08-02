@php
    $name = $name ?? 'Unknown';
    $submitted = $submitted ?? false;
    $needsSync = $needsSync ?? false;
    $role = $role ?? 'sender';
@endphp
@if(!$needsSync)
    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ $name }}
    </span>
@elseif($submitted)
    <span class="inline-flex items-center gap-1 rounded-full border border-green-500/20 bg-green-50 px-2 py-0.5 text-[10px] text-green-600">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ $name }}
    </span>
@elseif($role === 'sender')
    <span class="inline-flex animate-pulse items-center gap-1 rounded-full border border-gray-400/20 bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500">
        <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span>
        {{ $name }}
    </span>
@else
    <span class="inline-flex animate-pulse items-center gap-1 rounded-full border border-blue-500/20 bg-blue-50 px-2 py-0.5 text-[10px] text-blue-600">
        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
        {{ $name }}
    </span>
@endif
