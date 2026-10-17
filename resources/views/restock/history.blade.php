@extends('layouts.app')

@section('title', ($sheet ? $sheet->name.' — ' : '').'Restock change history')

@section('content')
@php
use App\Support\RestockCellHistoryLabels;

$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
];
if ($sheet) {
    $breadcrumbs[] = ['title' => $sheet->typeTag->name ?? 'Type', 'href' => route('restock.type.show', $sheet->typeTag)];
    $breadcrumbs[] = ['title' => $sheet->name, 'href' => route('restock.sheets.show', $sheet)];
}
$breadcrumbs[] = ['title' => 'Change history', 'href' => $sheet ? route('restock.sheets.history', $sheet) : route('restock.history.index')];

$formAction = $sheet ? route('restock.sheets.history', $sheet) : route('restock.history.index');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock change history</h1>
            <p class="text-sm text-gray-500">
                Who changed restock sheet quantities, when, and through which action.
                Scheduled jobs appear as <strong>Cron</strong> when no user is recorded.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($sheet)
                <a href="{{ route('restock.sheets.show', $sheet) }}"
                   class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Back to sheet
                </a>
            @else
                <a href="{{ route('restock.index') }}"
                   class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    All sheets
                </a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ $formAction }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="restock-history-filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @unless($sheet)
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-sheet">Sheet</label>
                <select id="filter-sheet" name="sheet_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All sheets</option>
                    @foreach($sheetOptions as $option)
                        <option value="{{ $option->id }}" @selected((int) ($filters['sheet_id'] ?? 0) === (int) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            @endunless

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-actor">Actor</label>
                <select id="filter-actor" name="actor_type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Any</option>
                    @foreach($actorLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['actor_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-source">Source</label>
                <select id="filter-source" name="source" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Any</option>
                    @foreach($sourceLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-action">Action</label>
                <select id="filter-action" name="action" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Any</option>
                    @foreach($actionLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-from">From date</label>
                <input type="date" id="filter-from" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600" for="filter-to">To date</label>
                <input type="date" id="filter-to" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Apply filters
            </button>
            <a href="{{ $formAction }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Clear
            </a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm" data-testid="restock-history-table">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-600">
                    <tr>
                        <th class="px-3 py-2">When</th>
                        <th class="px-3 py-2">Sheet / SKU</th>
                        <th class="px-3 py-2">Field</th>
                        <th class="px-3 py-2 text-right">Before → After</th>
                        <th class="px-3 py-2">Action</th>
                        <th class="px-3 py-2">Source</th>
                        <th class="px-3 py-2">Actor</th>
                        <th class="px-3 py-2">Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($entries as $entry)
                        @php
                            $sku = $entry->cell?->item ?? $entry->item;
                            $sheetName = $entry->sheet?->name ?? $entry->cell?->sheet?->name;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700">
                                {{ $entry->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900">{{ $sheetName ?? '—' }}</div>
                                <div class="font-mono text-xs text-gray-600">{{ $sku?->code ?? '—' }}</div>
                            </td>
                            <td class="px-3 py-2">{{ RestockCellHistoryLabels::fieldLabel($entry->field) }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-gray-800">
                                {{ number_format($entry->qty_before, 0, ',', '.') }}
                                →
                                {{ number_format($entry->qty_after, 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2">{{ RestockCellHistoryLabels::actionLabel($entry->action) }}</td>
                            <td class="px-3 py-2">{{ RestockCellHistoryLabels::sourceLabel($entry->source) }}</td>
                            <td class="px-3 py-2">
                                @if($entry->actor_type === RestockCellHistoryLabels::ACTOR_USER && $entry->user)
                                    <span title="User #{{ $entry->user_id }}">{{ $entry->user->username ?? $entry->user->name }}</span>
                                @else
                                    {{ RestockCellHistoryLabels::actorLabel($entry->actor_type ?? RestockCellHistoryLabels::ACTOR_SYSTEM) }}
                                @endif
                            </td>
                            <td class="max-w-xs truncate px-3 py-2 text-gray-600" title="{{ $entry->note }}">
                                @if($entry->transaction_id)
                                    <a href="{{ route('transactions.show', $entry->transaction_id) }}" class="text-blue-600 hover:underline">Txn #{{ $entry->transaction_id }}</a>
                                    @if($entry->note)
                                        <span class="text-gray-400"> · </span>
                                    @endif
                                @endif
                                {{ $entry->note }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">No history rows match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">
                {{ $entries->links() }}
            </div>
        @endif
    </div>

    <p class="text-xs text-gray-500">
        Rows older than six months are removed automatically by the scheduled purge job
        (<code class="rounded bg-gray-100 px-1">app:purge-restock-cell-histories</code>).
    </p>
</div>
@endsection
