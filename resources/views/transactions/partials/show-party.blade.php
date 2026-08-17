@php
    $accent = $accent ?? 'blue';
    $sideStatus = $sideStatus ?? null;
    $partyUrl = $party
        ? route('addrbook.type.show', ['type' => $party->type_slug, 'addrbook' => $party->id])
        : null;
@endphp
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50/50 px-4 py-3">
        <div class="text-xs font-bold tracking-wider text-gray-500 uppercase">{{ $label }} ({{ $direction }})</div>
        @if($iconArrow)
            <svg class="h-4 w-4 text-green-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        @else
            <svg class="h-4 w-4 text-blue-500 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        @endif
    </div>
    <div class="p-4">
        @if($party)
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-{{ $accent }}-100 text-sm font-bold text-{{ $accent }}-600">
                    {{ mb_substr($party->name, 0, 1) }}
                </div>
                <div class="min-w-0 flex-1">
                    <a href="{{ $partyUrl }}" class="block truncate text-base font-semibold leading-tight text-blue-600 hover:underline">{{ $party->name }}</a>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $party->type_name }} · ID {{ $party->id }}</p>
                    @if($sideStatus && $sideStatus['jubelioLocation'])
                        @if($sideStatus['submitted'])
                            <p class="mt-1 text-[10px] text-green-600">
                                {{ $sideStatus['isFromJubelio'] ?? false ? 'Tersinkron (Sistem)' : 'Tersinkron ke Jubelio' }}
                                <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                            </p>
                        @elseif($sideStatus['needsSync'])
                            <p class="mt-1 text-[10px] text-amber-600">
                                Menunggu sinkron
                                <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                            </p>
                        @else
                            <p class="mt-1 text-[10px] text-gray-500">Jubelio: {{ $sideStatus['jubelioLocation'] }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @else
            <div class="flex items-center gap-2 py-2 text-sm italic text-gray-400">
                <svg class="h-5 w-5 shrink-0 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $emptyText }}
            </div>
        @endif
    </div>
</div>
