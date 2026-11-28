@php
$nativePostedCount = isset($nativePostedCount) ? (int) $nativePostedCount : null;
@endphp
@if($nativePostedCount !== null)
<div class="flex flex-wrap items-center gap-2 text-sm text-gray-600" data-testid="omnichannel-native-post-sell-stats">
    <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-emerald-900">
        Native SELL posted: <span class="font-semibold" data-testid="omnichannel-post-sell-posted-count">{{ number_format($nativePostedCount) }}</span>
        <span class="text-[11px] text-emerald-800/80">(filter window)</span>
    </span>
    <span class="text-[11px] text-gray-500">Eligible/errors → Fulfillment settings · <code class="text-xs">omnichannel:refresh-post-sell-stats</code></span>
</div>
@endif