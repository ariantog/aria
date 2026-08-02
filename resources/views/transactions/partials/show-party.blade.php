@php
    $accent = $accent ?? 'blue';
    $sideStatus = $sideStatus ?? null;
@endphp
<div class="rounded-xl bg-white shadow-sm">
    <div class="flex flex-row items-center justify-between space-y-0 border-b bg-gray-50/50 p-6 pb-3">
        <div>
            <div class="text-sm font-bold tracking-widest text-gray-500 uppercase">{{ $label }} ({{ $direction }})</div>
            <div class="text-xs text-gray-400">{{ $sub }}</div>
        </div>
        @if($iconArrow)
            <svg class="h-4 w-4 text-green-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        @else
            <svg class="h-4 w-4 text-blue-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        @endif
    </div>
    <div class="p-6 pt-6">
        @if($party)
            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-{{ $accent }}-100 font-bold text-{{ $accent }}-600">
                        {{ mb_substr($party->name, 0, 1) }}
                    </div>
                    <div>
                        <p class="text-lg leading-tight font-bold">{{ $party->name }}</p>
                        <p class="mt-1 font-mono text-xs text-gray-500">ID: {{ $party->id }}</p>
                        @if($sideStatus && $sideStatus['jubelioLocation'])
                            @if($sideStatus['submitted'])
                                <div class="mt-1 flex flex-col gap-1">
                                    <span class="inline-flex w-fit items-center gap-1 rounded-md border border-green-500/20 bg-green-500/10 px-1.5 py-0.5 text-[9px] font-bold tracking-tighter text-green-600 uppercase">
                                        <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $sideStatus['isFromJubelio'] ? 'Tersinkron (Sistem)' : 'Tersinkron ke Jubelio' }}
                                    </span>
                                    <p class="ml-1 text-[10px] italic text-gray-500">Lok: {{ $sideStatus['jubelioLocation'] }}</p>
                                </div>
                            @elseif($sideStatus['needsSync'])
                                @php $isSender = $sideStatus['role'] === 'sender'; @endphp
                                <div class="mt-1 flex flex-col gap-1">
                                    <span class="inline-flex w-fit animate-pulse items-center gap-1 rounded-md border px-1.5 py-0.5 text-[9px] font-bold tracking-tighter uppercase {{ $isSender ? 'border-zinc-500/20 bg-zinc-500/10 text-zinc-500' : 'border-blue-500/20 bg-blue-500/10 text-blue-500' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isSender ? 'bg-zinc-500' : 'bg-blue-500' }}"></span>
                                        Menunggu Sinkron
                                    </span>
                                    <p class="ml-1 text-[10px] font-medium italic text-gray-500">Target: {{ $sideStatus['jubelioLocation'] }}</p>
                                </div>
                            @else
                                <div class="mt-1 flex flex-col gap-1 opacity-50">
                                    <span class="inline-flex w-fit items-center gap-1 rounded-md bg-gray-100 px-1.5 py-0.5 text-[9px] font-bold tracking-tighter text-gray-600 uppercase">
                                        <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Mapping Aktif
                                    </span>
                                    <p class="ml-1 text-[10px] italic text-gray-500">Terhubung ke: {{ $sideStatus['jubelioLocation'] }}</p>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                <hr>

                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded bg-gray-50 p-2 text-center">
                        <p class="mb-0.5 text-[10px] font-bold text-gray-500 uppercase">Type</p>
                        <p class="text-xs font-semibold">{{ $party->type_name }}</p>
                    </div>
                    <a href="{{ route('addrbook.type.show', ['type' => $party->type_slug, 'addrbook' => $party->id]) }}"
                       class="group flex flex-col items-center justify-center rounded border border-dashed p-2 transition-all hover:border-{{ $accent }}-400 hover:bg-{{ $accent }}-50">
                        <p class="mb-0.5 w-full text-center text-[10px] font-bold text-gray-500 uppercase group-hover:text-{{ $accent }}-500">View Details</p>
                        <svg class="h-3 w-3 transition-transform group-hover:-translate-y-0.5 group-hover:text-{{ $accent }}-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
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
