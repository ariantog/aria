@php
    $fmtCreated = function ($d) {
        if (! $d) {
            return '-';
        }
        $c = \Illuminate\Support\Carbon::parse($d);
        if ($c->year < 1) {
            return '-';
        }

        return $c->format('d/m/Y H:i');
    };

    $partyBlocks = [
        [
            'party' => $transaction->sender,
            'label' => $config['sender_label'],
            'direction' => 'From',
            'accent' => 'blue',
            'emptyText' => 'No sender info',
            'sideStatus' => [
                'submitted' => $transaction->a_synced,
                'needsSync' => in_array($transaction->sync_cek, ['S', 'B'], true),
                'jubelioLocation' => $transaction->jubelio_a,
                'isFromJubelio' => $transaction->is_from_jubelio,
                'role' => 'sender',
            ],
        ],
        [
            'party' => $transaction->receiver,
            'label' => $config['receiver_label'],
            'direction' => 'To',
            'accent' => 'green',
            'emptyText' => 'No receiver info',
            'sideStatus' => [
                'submitted' => $transaction->b_synced,
                'needsSync' => in_array($transaction->sync_cek, ['R', 'B'], true),
                'jubelioLocation' => $transaction->jubelio_b,
                'isFromJubelio' => $transaction->is_from_jubelio,
                'role' => 'receiver',
            ],
        ],
    ];
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="tx-show-overview">
    {{-- Primary facts --}}
    <div class="border-b border-gray-100 bg-gradient-to-br from-blue-50/80 via-white to-white px-4 py-4 sm:px-6 sm:py-5">
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 sm:gap-6">
            <div class="min-w-0">
                <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase sm:text-xs">Date</div>
                <div class="mt-1 text-xl font-bold tabular-nums text-gray-900 sm:text-2xl" data-testid="tx-show-date">{{ $fmtDate($transaction->date) }}</div>
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase sm:text-xs">Type</div>
                <div class="mt-1.5">
                    <span class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-1 text-sm font-semibold capitalize text-white sm:text-base" data-testid="tx-show-type">{{ $config['type_slug'] }}</span>
                </div>
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase sm:text-xs">Total Items</div>
                <div class="mt-1 text-xl font-bold tabular-nums text-gray-900 sm:text-2xl" data-testid="tx-total-items-summary">{{ $fmt($transaction->displayTotalItems()) }}</div>
            </div>
            <div class="min-w-0 col-span-2 sm:col-span-1">
                <div class="flex items-start justify-between gap-2 sm:block">
                    <div>
                        <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase sm:text-xs">Grand Total</div>
                        <div class="mt-0.5 text-[10px] font-medium text-blue-600 sm:text-xs">IDR</div>
                        <div data-testid="tx-grand-total-hero" class="{{ $grandTotalHeroClass }} tabular-nums break-all text-blue-700">{{ $grandTotalFormatted }}</div>
                    </div>
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[10px] font-semibold sm:hidden {{ $status['color'] }}">{{ $status['label'] }}</span>
                </div>
            </div>
        </div>
        <div class="mt-4 hidden items-center justify-end sm:flex">
            <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold {{ $status['color'] }}">{{ $status['label'] }}</span>
        </div>
    </div>

    {{-- Sender & receiver --}}
    <div class="grid grid-cols-1 divide-y divide-gray-100 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
        @foreach($partyBlocks as $contact)
            @php
                $party = $contact['party'];
                $sideStatus = $contact['sideStatus'];
                $partyUrl = \App\Models\Addrbook::transactionsUrlFor($party);
                $accent = $contact['accent'];
            @endphp
            <div class="px-4 py-4 sm:px-6 sm:py-5" data-testid="tx-show-party-{{ $sideStatus['role'] }}">
                <div class="text-[10px] font-bold tracking-wider text-gray-400 uppercase sm:text-xs">
                    {{ $contact['label'] }} <span class="font-semibold text-gray-500">({{ $contact['direction'] }})</span>
                </div>
                @if($party)
                    <div class="mt-3 flex items-start gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-{{ $accent }}-100 text-base font-bold text-{{ $accent }}-700 sm:h-12 sm:w-12 sm:text-lg">
                            {{ mb_substr($party->name, 0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ $partyUrl }}" class="block truncate text-lg font-bold leading-tight text-blue-600 hover:underline sm:text-xl">{{ $party->name }}</a>
                            <p class="mt-1 text-xs text-gray-500 sm:text-sm">{{ $party->type_name }} · ID {{ $party->id }}</p>
                            @if($sideStatus['jubelioLocation'])
                                @if($sideStatus['submitted'])
                                    <p class="mt-1.5 text-xs text-green-600">
                                        {{ $sideStatus['isFromJubelio'] ?? false ? 'Tersinkron (Sistem)' : 'Tersinkron ke Jubelio' }}
                                        <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                                    </p>
                                @elseif($sideStatus['needsSync'])
                                    <p class="mt-1.5 text-xs text-amber-600">
                                        Menunggu sinkron
                                        <span class="text-gray-400">· {{ $sideStatus['jubelioLocation'] }}</span>
                                    </p>
                                @else
                                    <p class="mt-1.5 text-xs text-gray-500">Jubelio: {{ $sideStatus['jubelioLocation'] }}</p>
                                @endif
                            @endif
                        </div>
                    </div>
                @else
                    <p class="mt-3 text-sm italic text-gray-400">{{ $contact['emptyText'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Internal / audit metadata --}}
    <div class="border-t border-gray-100 bg-gray-50/90 px-4 py-3 sm:px-6">
        <div class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">Internal</div>
        <dl class="mt-2 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3 sm:gap-4">
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">Submit source</dt>
                <dd class="mt-1">
                    @if((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_JUBELIO)
                        <span class="inline-flex items-center gap-1 rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-600" data-testid="tx-show-submit-source">
                            cron jubelio
                        </span>
                    @elseif((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_MANUAL)
                        <span class="inline-flex items-center gap-1 rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-bold text-blue-600" data-testid="tx-show-submit-source">
                            aria submit
                        </span>
                    @else
                        <span class="text-xs font-medium text-gray-600" data-testid="tx-show-submit-source">submit type {{ (int) $transaction->submit_type }}</span>
                    @endif
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">Created by</dt>
                <dd class="mt-1 font-medium text-gray-800" data-testid="tx-show-created-by">
                    @if($transaction->user)
                        {{ $transaction->user->name }}
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-xs font-medium text-gray-500">Created date</dt>
                <dd class="mt-1 font-medium tabular-nums text-gray-800" data-testid="tx-show-created-at">{{ $fmtCreated($transaction->created_at) }}</dd>
            </div>
        </dl>
        @if($jubelioSync['show_ui'] ?? false)
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/80 pt-3 text-sm">
                <span class="font-semibold text-blue-600">Sinkron Jubelio</span>
                <a href="/jubelio-transaction/{{ $transaction->id }}/detail-sync"
                   class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-blue-50">
                    Kelola Sinkron
                    <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
        @endif
    </div>
</div>
