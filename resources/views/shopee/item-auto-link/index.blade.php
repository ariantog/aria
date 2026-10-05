@extends('layouts.app')

@section('title', 'Shopee Auto Link')

@section('content')
@php
use App\Models\ShopeeItemLinkAttempt;

$breadcrumbs = [
    ['title' => 'Shopee Warehouse Mapping', 'href' => route('shopee.sync.index')],
    ['title' => 'Auto Link', 'href' => route('shopee.auto-link.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Shopee Auto Link</h1>
        <p class="mt-1 text-sm text-gray-500">
            Cron runs <strong>hourly</strong> and tries up to <strong>50</strong> unlinked SKUs per tick — same rules as manual Link SKU: exact <span class="font-mono">Kode Variasi</span> / <span class="font-mono">model_sku</span> only (never parent SKU when the listing has size/color rows). Uses name search + catalog scan.
            Same eligibility as Jubelio auto-link: stock in a Shopee-mapped warehouse, rolling id window + {{ $stats['retry_campaign_days'] }}-day campaign, {{ $stats['retry_spacing_hours'] }}h spacing, max {{ $stats['max_attempts'] }} failed tries, ~{{ $stats['calls_cap'] }} Shopee API calls/hour.
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if(! ($stockReady ?? false))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        STOCK CHECKER belum siap — authorize di
        <a href="{{ route('shopee.sync.index') }}" class="font-medium underline">Warehouse Map</a>
        (OAuth terpisah dari Shopee Ads).
    </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="shopee-auto-link-dashboard">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                @if($runner->paused)
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase text-amber-800">Paused</span>
                @else
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">Active</span>
                @endif
                <span class="text-sm text-gray-600">Last run: {{ $runner->last_run_at?->diffForHumans() ?? '—' }}</span>
            </div>
            <div class="text-sm text-gray-600">
                API this hour: <strong>{{ $stats['calls_this_hour'] }}</strong> / {{ $stats['calls_cap'] }}
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-6">
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-emerald-700">{{ $stats['linked_today'] }}</div>
                <div class="text-xs text-gray-500">Linked today</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-gray-700">{{ $stats['no_match_today'] }}</div>
                <div class="text-xs text-gray-500">No match today</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-amber-700">{{ $stats['ambiguous_today'] }}</div>
                <div class="text-xs text-gray-500">Ambiguous today</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-red-700">{{ $stats['api_error_today'] }}</div>
                <div class="text-xs text-gray-500">API errors today</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center md:col-span-2">
                <div class="text-lg font-bold text-blue-700">{{ $stats['eligible_in_window'] }}</div>
                <div class="text-xs text-gray-500">Unlinked eligible in rolling window</div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
            @if($runner->paused)
            <form method="POST" action="{{ route('shopee.auto-link.resume') }}">@csrf
                <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">Resume cron</button>
            </form>
            @else
            <form method="POST" action="{{ route('shopee.auto-link.pause') }}">@csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Pause cron</button>
            </form>
            @endif
            <form method="POST" action="{{ route('shopee.auto-link.run-batch') }}" class="flex items-center gap-2">@csrf
                <input type="number" name="limit" min="1" max="50" value="50" class="w-16 rounded-md border border-gray-300 px-2 py-2 text-sm">
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Run batch now</button>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gray-50 px-6 py-3">
            <h2 class="font-semibold text-gray-900">Recent attempts</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Time</th>
                        <th class="px-4 py-2">SKU</th>
                        <th class="px-4 py-2">Search q</th>
                        <th class="px-4 py-2">Outcome</th>
                        <th class="px-4 py-2">Shopee IDs</th>
                        <th class="px-4 py-2">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($attempts as $attempt)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2 text-gray-500">{{ $attempt->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2 font-mono">
                            @if($attempt->item)
                                <a href="{{ route('items.shopee', $attempt->item_id) }}" class="text-orange-600 hover:underline">{{ $attempt->item->code }}</a>
                            @else
                                #{{ $attempt->item_id }}
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-gray-600">{{ $attempt->search_q ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @php
                            $badge = match($attempt->outcome) {
                                ShopeeItemLinkAttempt::OUTCOME_LINKED => 'bg-emerald-100 text-emerald-800',
                                ShopeeItemLinkAttempt::OUTCOME_AMBIGUOUS => 'bg-amber-100 text-amber-800',
                                ShopeeItemLinkAttempt::OUTCOME_API_ERROR => 'bg-red-100 text-red-800',
                                ShopeeItemLinkAttempt::OUTCOME_NO_MATCH => 'bg-gray-100 text-gray-700',
                                default => 'bg-gray-50 text-gray-500',
                            };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $attempt->outcome }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono text-xs">
                            @if($attempt->matched_shopee_item_id)
                                {{ $attempt->matched_shopee_item_id }}@if($attempt->matched_shopee_model_id)/{{ $attempt->matched_shopee_model_id }}@endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ $attempt->error_message ?? '' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">No attempts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
