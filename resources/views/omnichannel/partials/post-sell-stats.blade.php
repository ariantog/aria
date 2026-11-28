@php
/** @var array<string, mixed>|null $nativePostSellStats */
$nativePostSellStats = $nativePostSellStats ?? null;
$jubelioActive = (bool) ($jubelioActive ?? config('services.jubelio.active'));
$jubelioPendingSell = (int) ($jubelioPendingSell ?? $jubelioPending ?? 0);
@endphp
@if($nativePostSellStats !== null)
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-5" data-testid="omnichannel-native-post-sell-stats">
    <div class="rounded-xl border border-emerald-300 bg-emerald-50/80 p-4 shadow-sm dark:border-emerald-800/50 dark:bg-emerald-950/20">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Native SELL posted</p>
        <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100" data-testid="omnichannel-post-sell-posted-count">
            {{ number_format((int) ($nativePostSellStats['posted'] ?? 0)) }}
        </p>
        <p class="text-[11px] text-emerald-800/80 dark:text-emerald-200/80">posted_transaction_id</p>
    </div>
    <div class="rounded-xl border border-violet-300 bg-violet-50/80 p-4 shadow-sm dark:border-violet-800/50 dark:bg-violet-950/20">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">Eligible to post</p>
        <p class="mt-1 text-2xl font-bold text-violet-900 dark:text-violet-100" data-testid="omnichannel-post-sell-eligible-count">
            {{ number_format((int) ($nativePostSellStats['eligible_to_post'] ?? 0)) }}
        </p>
        <p class="text-[11px] text-violet-800/80 dark:text-violet-200/80">
            Gate <span class="font-mono">{{ $nativePostSellStats['gate'] ?? 'shipped' }}</span>
            @if(! empty($nativePostSellStats['post_sell_dry_run']))
            · dry-run
            @elseif(! ($nativePostSellStats['post_sells_enabled'] ?? false))
            · cron off
            @endif
        </p>
    </div>
    <div class="rounded-xl border border-amber-300 bg-amber-50/80 p-4 shadow-sm dark:border-amber-800/50 dark:bg-amber-950/20">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">Post errors</p>
        <p class="mt-1 text-2xl font-bold text-amber-950 dark:text-amber-50" data-testid="omnichannel-post-sell-errors-count">
            {{ number_format((int) ($nativePostSellStats['errors'] ?? 0)) }}
        </p>
        <p class="text-[11px] text-amber-900/80">Gudang / baris / window</p>
    </div>
    @if($jubelioActive)
    <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 shadow-sm opacity-90 dark:border-gray-600 dark:bg-gray-800/40">
        <p class="text-[10px] font-semibold uppercase text-gray-500">Jubelio sell pending</p>
        <p class="mt-1 text-xl font-semibold text-gray-700 dark:text-gray-200">{{ number_format($jubelioPendingSell) }}</p>
        <p class="text-[11px] text-gray-500">Cutover legacy · <a href="{{ route('jubelio.index') }}" class="text-blue-700 hover:underline">Jubelio</a></p>
    </div>
    @endif
</div>
@if(! empty($nativePostSellStats['stats_updated_at']))
<p class="mt-1 text-[11px] text-gray-500" data-testid="omnichannel-post-sell-stats-refreshed">
    KPI cache
    @if(! empty($nativePostSellStats['stats_stale']))
    <span class="text-amber-700">(stale)</span>
    @endif
    · terakhir {{ \Illuminate\Support\Carbon::parse($nativePostSellStats['stats_updated_at'])->diffForHumans() }}
</p>
@endif
@endif