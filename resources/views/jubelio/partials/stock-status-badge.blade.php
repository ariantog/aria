@php $status = $status ?? ''; @endphp
@switch($status)
    @case('completed')
        <span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-green-50 px-2 py-0.5 text-xs font-medium uppercase text-green-600">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Completed
        </span>
        @break
    @case('processing')
        <span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-blue-50 px-2 py-0.5 text-xs font-medium uppercase text-blue-600">
            <svg class="h-3 w-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Processing
        </span>
        @break
    @case('stopped')
        <span class="inline-flex items-center gap-1 rounded border border-gray-200 bg-yellow-50 px-2 py-0.5 text-xs font-medium uppercase text-yellow-600">
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Stopped (200)
        </span>
        @break
    @default
        <span class="inline-flex items-center rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-medium uppercase text-gray-600">{{ $status }}</span>
@endswitch
