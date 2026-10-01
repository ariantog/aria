@php
    $party = $contact['party'];
    $sideStatus = $contact['sideStatus'] ?? null;
    $partyUrl = \App\Models\Addrbook::transactionsUrlFor($party);
    $padding = $padding ?? 'px-4 py-4';
@endphp
<div class="{{ $padding }}" data-testid="tx-show-party-{{ $contact['role'] }}">
    <div class="text-[10px] font-bold tracking-wider text-gray-400 uppercase dark:text-gray-500 sm:text-xs">
        {{ $contact['label'] }} <span class="font-semibold text-gray-500 dark:text-gray-400">({{ $contact['direction'] }})</span>
    </div>
    @if($party)
        <div class="mt-3 flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $contact['accentBg'] }} sm:h-11 sm:w-11">
                {{ mb_substr($party->name, 0, 1) }}
            </div>
            <div class="min-w-0 flex-1">
                <a href="{{ $partyUrl }}" class="block truncate text-base font-bold leading-tight text-blue-600 hover:underline dark:text-blue-400 sm:text-lg">{{ $party->name }}</a>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $party->type_name }} · ID {{ $party->id }}</p>
                @if($sideStatus && ($sideStatus['jubelioLocation'] ?? null))
                    @if($sideStatus['submitted'])
                        <p class="mt-1.5 text-[10px] text-green-600 sm:text-xs">
                            {{ ($sideStatus['isFromJubelio'] ?? false) ? 'Tersinkron (Sistem)' : 'Tersinkron ke Jubelio' }}
                            <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                        </p>
                    @elseif($sideStatus['needsSync'])
                        <p class="mt-1.5 text-[10px] text-amber-600 sm:text-xs">
                            Menunggu sinkron
                            <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                        </p>
                    @else
                        <p class="mt-1.5 text-[10px] text-gray-500 sm:text-xs">Jubelio: {{ $sideStatus['jubelioLocation'] }}</p>
                    @endif
                @endif
            </div>
        </div>
    @else
        <p class="mt-2 text-sm italic text-gray-400">{{ $contact['emptyText'] }}</p>
    @endif
</div>
