@extends('layouts.app')

@section('title', 'Jubelio Auto Link')

@section('content')
@php
use App\Models\JubelioItemLinkAttempt;

$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Auto Link', 'href' => route('jubelio.auto-link.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4 sm:p-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Jubelio Auto Link</h1>
        <p class="mt-1 text-sm text-gray-500">
            Cron searches <span class="font-mono">inventory/items/to-stock</span> and links when Jubelio <span class="font-mono">item_code</span> matches exactly.
            SKUs need stock in a Jubelio-mapped warehouse, are at least 1 day old, and get up to 5 attempts (1 day apart).
        </p>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
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
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-blue-700">{{ $stats['eligible_recent'] }}</div>
                <div class="text-xs text-gray-500">Eligible ≤30d</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center">
                <div class="text-lg font-bold text-blue-600">{{ $stats['eligible_rolling'] }}</div>
                <div class="text-xs text-gray-500">Eligible older</div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
            @if($runner->paused)
            <form method="POST" action="{{ route('jubelio.auto-link.resume') }}">@csrf
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Resume cron</button>
            </form>
            @else
            <form method="POST" action="{{ route('jubelio.auto-link.pause') }}">@csrf
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Pause cron</button>
            </form>
            @endif
            <form method="POST" action="{{ route('jubelio.auto-link.run-batch') }}" class="flex items-center gap-2">@csrf
                <input type="number" name="limit" min="1" max="50" value="4" class="w-16 rounded-md border border-gray-300 px-2 py-2 text-sm">
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Run batch now</button>
            </form>
            <a href="{{ route('jubelio.item-links.index', ['view' => 'items', 'link' => 'auto_failed']) }}" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">View auto-link failures</a>
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
                        <th class="px-4 py-2">Jubelio ID</th>
                        <th class="px-4 py-2">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($attempts as $attempt)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2 text-gray-500">{{ $attempt->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-2 font-mono">
                            @if($attempt->item)
                                <a href="{{ route('items.jubelio', $attempt->item_id) }}" class="text-blue-600 hover:underline">{{ $attempt->item->code }}</a>
                            @else
                                #{{ $attempt->item_id }}
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-gray-600">{{ $attempt->search_q ?: '—' }}</td>
                        <td class="px-4 py-2">
                            @php
                            $badge = match($attempt->outcome) {
                                JubelioItemLinkAttempt::OUTCOME_LINKED => 'bg-emerald-100 text-emerald-800',
                                JubelioItemLinkAttempt::OUTCOME_AMBIGUOUS => 'bg-amber-100 text-amber-800',
                                JubelioItemLinkAttempt::OUTCOME_API_ERROR => 'bg-red-100 text-red-800',
                                JubelioItemLinkAttempt::OUTCOME_NO_MATCH => 'bg-gray-100 text-gray-700',
                                default => 'bg-gray-50 text-gray-500',
                            };
                            @endphp
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ $attempt->outcome }}</span>
                        </td>
                        <td class="px-4 py-2 font-mono">{{ $attempt->matched_jubelio_item_id ?? '—' }}</td>
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
