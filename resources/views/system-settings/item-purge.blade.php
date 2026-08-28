@extends('layouts.app')

@section('title', 'Selective Item Purge')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Data Retention', 'href' => route('data-retention.index')],
    ['title' => 'Selective Item Purge', 'href' => route('data-retention.item-purge.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4" x-data="{
    toggleKeep(id, checked) {
        const params = new URLSearchParams(window.location.search);
        let keeps = params.getAll('keep[]');
        const key = String(id);
        if (checked) {
            if (! keeps.includes(key)) {
                keeps.push(key);
            }
        } else {
            keeps = keeps.filter((value) => value !== key);
        }
        params.delete('keep[]');
        keeps.forEach((value) => params.append('keep[]', value));
        window.location.search = params.toString();
    }
}">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Selective Item Purge</h1>
            <p class="text-sm text-gray-500">
                Hard-delete orphan items with <strong>no transaction lines</strong>, even when warehouse stock &gt; 0.
                Soft-deleted items are included. Item groups with no remaining items are purged afterward.
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Orphans are listed regardless of <code class="rounded bg-gray-100 px-1">created_at</code> so legacy rows
                touched by migration are not hidden. Check <strong>Keep</strong> to exclude an item from purge.
            </p>
        </div>
        <a href="{{ route('data-retention.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Data Retention</a>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">Reference cutoff year</label>
            <input type="number" name="cutoff_year" value="{{ $cutoffYear }}" min="2000" max="2100" class="h-9 w-28 rounded-md border border-gray-300 px-3 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">Item type</label>
            <select name="item_type" class="h-9 rounded-md border border-gray-300 px-3 text-sm">
                <option value="">All types</option>
                @foreach($itemTypes as $value => $label)
                <option value="{{ $value }}" @selected($itemType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @foreach($keepIds as $keepId)
        <input type="hidden" name="keep[]" value="{{ $keepId }}">
        @endforeach
        <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Preview</button>
    </form>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 p-4 pb-0">
            <h2 class="text-lg font-semibold">Preview</h2>
            <div class="text-sm text-gray-500">
                <span>{{ number_format($preview->total()) }} orphan item(s)</span>
                @if($keepIds !== [])
                <span class="mx-1">·</span>
                <span>{{ number_format(count($keepIds)) }} kept</span>
                @endif
                <span class="mx-1">·</span>
                <span class="font-medium text-red-700">{{ number_format($purgeCount) }} to purge</span>
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium" title="Check to exclude this item from purge">Keep</th>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">SKU</th>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Created</th>
                        <th class="px-3 py-2 font-medium">Earliest tx</th>
                        <th class="px-3 py-2 font-medium">Deleted</th>
                        <th class="px-3 py-2 text-right font-medium">Warehouse qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($preview as $row)
                    <tr @class(['bg-amber-50/60' => in_array($row['id'], $keepIds, true)])>
                        <td class="px-3 py-2">
                            <input type="checkbox"
                                   class="h-4 w-4 rounded border-gray-300"
                                   aria-label="Keep item #{{ $row['id'] }}"
                                   @checked(in_array($row['id'], $keepIds, true))
                                   @change="toggleKeep({{ $row['id'] }}, $event.target.checked)">
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">#{{ $row['id'] }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $row['code'] }}</td>
                        <td class="px-3 py-2">{{ $row['name'] }}</td>
                        <td class="px-3 py-2">{{ $row['type'] }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('Y-m-d') }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $row['earliest_tx_date'] ? \Illuminate\Support\Carbon::parse($row['earliest_tx_date'])->format('Y-m-d') : '—' }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $row['deleted_at'] ? \Illuminate\Support\Carbon::parse($row['deleted_at'])->format('Y-m-d') : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['warehouse_qty'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">No matching items for this preview.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $preview, 'label' => 'items'])
    </div>

    <form method="POST" action="{{ route('data-retention.item-purge.purge') }}" class="rounded-xl border border-red-200 bg-red-50 p-4"
          onsubmit="return confirm('Permanently delete {{ number_format($purgeCount) }} orphan item(s)? Kept items are excluded.');">
        @csrf
        <input type="hidden" name="cutoff_year" value="{{ $cutoffYear }}">
        @if($itemType !== null)
        <input type="hidden" name="item_type" value="{{ $itemType }}">
        @endif
        @foreach($keepIds as $keepId)
        <input type="hidden" name="keep_ids[]" value="{{ $keepId }}">
        @endforeach
        <div class="text-sm font-semibold text-red-900">Execute purge</div>
        <p class="mt-1 text-sm text-red-800">
            Purges every orphan item matching the filters above, except rows marked <strong>Keep</strong>
            ({{ number_format(count($keepIds)) }} kept, {{ number_format($purgeCount) }} will be deleted).
            Use pagination to mark items on other pages before submitting.
        </p>
        <div class="mt-3 flex flex-wrap items-end gap-2">
            <div class="min-w-[16rem] flex-1">
                <label class="mb-1 block text-xs font-medium text-red-800">Type PURGE-ITEMS-WITH-STOCK to confirm</label>
                <input type="text" name="confirm" required placeholder="PURGE-ITEMS-WITH-STOCK" class="h-9 w-full max-w-md rounded-md border border-red-300 bg-white px-3 text-sm font-mono">
            </div>
            <button type="submit" class="h-9 rounded-md bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700" @disabled($purgeCount === 0)>
                Purge {{ number_format($purgeCount) }} item(s)
            </button>
        </div>
    </form>
</div>
@endsection
