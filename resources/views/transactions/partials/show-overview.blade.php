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
@endphp

<div class="shrink-0" data-testid="tx-show-overview">
    <div class="grid grid-cols-1 items-stretch gap-3 lg:grid-cols-3 lg:gap-4">
        {{-- Key metrics --}}
        <div class="flex h-full flex-col overflow-hidden rounded-xl border border-blue-100 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800">
            <div class="h-1.5 w-full shrink-0 bg-blue-600"></div>
            <div class="flex flex-1 flex-col space-y-4 px-4 py-4 sm:px-5 sm:py-5">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Date</div>
                        <div class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-50 sm:text-2xl" data-testid="tx-show-date">{{ $fmtDate($transaction->date) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Type</div>
                        <div class="mt-1.5">
                            <span class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-1 text-sm font-semibold capitalize text-white dark:bg-gray-100 dark:text-gray-900" data-testid="tx-show-type">{{ $config['type_slug'] }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Total Items</div>
                        <div class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-50 sm:text-2xl" data-testid="tx-total-items-summary">{{ $fmt($transaction->displayTotalItems()) }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Status</div>
                        <div class="mt-1.5">
                            <span class="inline-flex items-center rounded-full px-3 py-0.5 text-xs font-semibold {{ $status['color'] }}">{{ $status['label'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-auto rounded-lg border border-dashed border-blue-200 bg-blue-50/50 px-3 py-3 dark:border-blue-900/50 dark:bg-blue-950/30">
                    <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400">Grand Total</div>
                    <div class="mt-0.5 text-[10px] font-medium text-blue-600 dark:text-blue-400">IDR</div>
                    <div data-testid="tx-grand-total-hero" class="{{ $grandTotalHeroClass }} tabular-nums break-all text-blue-700 dark:text-blue-300">{{ $grandTotalFormatted }}</div>
                </div>
            </div>
        </div>

        {{-- Mobile: combined parties + internal --}}
        <div class="flex h-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-600 dark:bg-gray-800 lg:hidden">
            <div class="border-b border-gray-100 bg-gray-50/50 px-3 py-2 text-[10px] font-bold tracking-wider text-gray-500 uppercase dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">Contacts</div>
            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach([
                    ['party' => $transaction->sender, 'label' => $config['sender_label'], 'direction' => 'From', 'accentBg' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-200', 'emptyText' => 'No sender info', 'role' => 'sender'],
                    ['party' => $transaction->receiver, 'label' => $config['receiver_label'], 'direction' => 'To', 'accentBg' => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-200', 'emptyText' => 'No receiver info', 'role' => 'receiver'],
                ] as $contact)
                <div class="px-3 py-3" data-testid="tx-show-party-{{ $contact['role'] }}">
                    <div class="text-[10px] font-bold tracking-wider text-gray-400 uppercase">{{ $contact['label'] }} ({{ $contact['direction'] }})</div>
                    @if($contact['party'])
                        @php $contactUrl = $contact['party']->transactionsUrl(); @endphp
                        <div class="mt-2 flex items-center gap-2.5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $contact['accentBg'] }}">
                                {{ mb_substr($contact['party']->name, 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <a href="{{ $contactUrl }}" class="block truncate text-base font-bold text-blue-600 hover:underline dark:text-blue-400">{{ $contact['party']->name }}</a>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $contact['party']->type_name }} · ID {{ $contact['party']->id }}</p>
                            </div>
                        </div>
                    @else
                        <p class="mt-2 text-sm italic text-gray-400">{{ $contact['emptyText'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="mt-auto border-t border-gray-100 px-3 py-3 dark:border-gray-700">
                <div class="mb-2 text-[10px] font-bold tracking-wider text-gray-400 uppercase dark:text-gray-500">Internal</div>
                @include('transactions.partials.show-internal-meta', [
                    'transaction' => $transaction,
                    'jubelioSync' => $jubelioSync ?? [],
                    'slot' => 'all',
                    'fmtCreated' => $fmtCreated,
                ])
            </div>
        </div>

        {{-- Desktop: party cards with internal footers --}}
        <div class="hidden lg:contents">
            @include('transactions.partials.show-party', [
                'party' => $transaction->sender,
                'label' => $config['sender_label'],
                'direction' => 'From',
                'accent' => 'blue',
                'iconArrow' => false,
                'emptyText' => 'No sender info',
                'transaction' => $transaction,
                'jubelioSync' => $jubelioSync ?? [],
                'internalSlot' => 'sender',
                'fmtCreated' => $fmtCreated,
                'sideStatus' => [
                    'submitted' => $transaction->a_synced,
                    'needsSync' => in_array($transaction->sync_cek, ['S', 'B'], true),
                    'jubelioLocation' => $transaction->jubelio_a,
                    'isFromJubelio' => $transaction->is_from_jubelio,
                    'role' => 'sender',
                ],
            ])

            @include('transactions.partials.show-party', [
                'party' => $transaction->receiver,
                'label' => $config['receiver_label'],
                'direction' => 'To',
                'accent' => 'green',
                'iconArrow' => true,
                'emptyText' => 'No receiver info',
                'transaction' => $transaction,
                'jubelioSync' => $jubelioSync ?? [],
                'internalSlot' => 'receiver',
                'fmtCreated' => $fmtCreated,
                'sideStatus' => [
                    'submitted' => $transaction->b_synced,
                    'needsSync' => in_array($transaction->sync_cek, ['R', 'B'], true),
                    'jubelioLocation' => $transaction->jubelio_b,
                    'isFromJubelio' => $transaction->is_from_jubelio,
                    'role' => 'receiver',
                ],
            ])
        </div>
    </div>
</div>
