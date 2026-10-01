@php
    $fmtCreated = $fmtCreated ?? function ($d) {
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

<div class="flex h-full flex-1 flex-col space-y-4 text-sm" data-testid="tx-show-internal-all">
    <div>
        <div class="text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Submit source</div>
        <div class="mt-1.5">
            @if((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_JUBELIO)
                <span class="inline-flex items-center rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-xs font-bold text-amber-600 dark:text-amber-400" data-testid="tx-show-submit-source">cron jubelio</span>
            @elseif((int) $transaction->submit_type === \App\Models\Transaction::SUBMIT_TYPE_MANUAL)
                <span class="inline-flex items-center rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-xs font-bold text-blue-600 dark:text-blue-400" data-testid="tx-show-submit-source">aria submit</span>
            @else
                <span class="text-xs font-medium text-gray-600 dark:text-gray-300" data-testid="tx-show-submit-source">submit type {{ (int) $transaction->submit_type }}</span>
            @endif
        </div>
    </div>
    <div>
        <div class="text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Created by</div>
        <div class="mt-1.5 font-medium text-gray-800 dark:text-gray-100" data-testid="tx-show-created-by">
            @if($transaction->user)
                {{ $transaction->user->name }}
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
    </div>
    <div>
        <div class="text-[10px] font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Created date</div>
        <div class="mt-1.5 font-medium tabular-nums text-gray-800 dark:text-gray-100" data-testid="tx-show-created-at">{{ $fmtCreated($transaction->created_at) }}</div>
    </div>
    @if($jubelioSync['show_ui'] ?? false)
        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/80 pt-4 dark:border-gray-700">
            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">Sinkron Jubelio</span>
            <a href="/jubelio-transaction/{{ $transaction->id }}/detail-sync"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
                Kelola Sinkron
                <svg class="ml-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    @endif
</div>
