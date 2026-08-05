<div class="rounded-xl bg-white shadow-sm">
    <div class="flex flex-row items-center justify-between space-y-0 border-b bg-gray-50/50 p-6 pb-3">
        <div>
            <div class="text-sm font-bold tracking-widest text-gray-500 uppercase">{{ $label }} ({{ $direction }})</div>
            <div class="text-xs text-gray-400">{{ $sub }}</div>
        </div>
        @if($iconArrow)
            <svg class="h-4 w-4 text-zinc-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        @else
            <svg class="h-4 w-4 text-zinc-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        @endif
    </div>
    <div class="p-6 pt-6">
        @if($party)
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 font-bold text-zinc-600">
                        {{ mb_substr($party->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="text-lg leading-tight font-bold">{{ $party->name }}</p>
                        <p class="mt-1 font-mono text-xs text-gray-500">ID: {{ $party->id }}</p>
                    </div>
                </div>

                <hr>

                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded bg-gray-50 p-2 text-center">
                        <p class="mb-0.5 text-[10px] font-bold text-gray-500 uppercase">Type</p>
                        <p class="text-xs font-semibold">{{ $party->type_name }}</p>
                    </div>
                    <a href="{{ route('addrbook.type.show', ['type' => $party->type_slug, 'addrbook' => $party->id]) }}"
                       class="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-zinc-400 hover:bg-zinc-50">
                        <p class="mb-0.5 w-full text-center text-[10px] font-bold text-gray-500 uppercase group-hover:text-zinc-900">View Detail</p>
                        <svg class="h-3 w-3 transition-transform group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-6 text-gray-500">
                <svg class="mb-2 h-8 w-8 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-medium italic">{{ $emptyText }}</p>
            </div>
        @endif
    </div>
</div>
