@extends('layouts.app')

@section('title', 'Selective Item Purge')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Data Retention', 'href' => route('data-retention.index')],
    ['title' => 'Selective Item Purge', 'href' => route('data-retention.item-purge.index')],
];
$pageItemCount = $preview->count();
$pageItemIds = collect($preview->items())->pluck('id')->values()->all();
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Selective Item Purge</h1>
            <p class="text-sm text-gray-500">
                Hard-delete orphan items with <strong>no transaction lines</strong> and <strong>id &le; max id</strong>,
                even when warehouse stock &gt; 0. Soft-deleted items are included.
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Check <strong>Keep</strong> to exclude a row on this page from purge. Submit purges only
                <strong>unchecked rows on the current page</strong>; visit other pages separately to purge them.
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
            <label class="mb-1 block text-xs font-medium text-gray-500">Max item id</label>
            <input type="number" name="max_id" value="{{ $maxId }}" min="1" class="h-9 w-32 rounded-md border border-gray-300 px-3 text-sm" placeholder="e.g. 10000">
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
        <button type="submit" class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Preview</button>
    </form>

    <form method="POST" action="{{ route('data-retention.item-purge.purge') }}"
          class="rounded-xl border border-gray-200 bg-white shadow-sm"
          x-data="{
              pageItemIds: @js($pageItemIds),
              keepIds: [],
              pagePurgeCount() {
                  return this.pageItemIds.filter((id) => ! this.keepIds.includes(id)).length;
              },
              syncKeep(id, checked) {
                  if (checked) {
                      if (! this.keepIds.includes(id)) {
                          this.keepIds.push(id);
                      }
                  } else {
                      this.keepIds = this.keepIds.filter((value) => value !== id);
                  }
              }
          }"
          @submit="if (! confirm('Permanently delete ' + pagePurgeCount() + ' orphan item(s) on this page? Other pages are not affected.')) { $event.preventDefault(); }">
        @csrf
        <input type="hidden" name="max_id" value="{{ $maxId }}">
        <input type="hidden" name="page" value="{{ $preview->currentPage() }}">
        @if($itemType !== null)
        <input type="hidden" name="item_type" value="{{ $itemType }}">
        @endif

        <div class="flex flex-wrap items-center justify-between gap-2 p-4 pb-0">
            <h2 class="text-lg font-semibold">Preview</h2>
            <div class="text-sm text-gray-500">
                <span>{{ number_format($totalCandidates) }} candidate(s) total</span>
                <span class="mx-1">·</span>
                <span>page {{ $preview->currentPage() }} of {{ max(1, $preview->lastPage()) }}</span>
                <span class="mx-1">·</span>
                <span>{{ number_format($pageItemCount) }} on this page</span>
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium" title="Check to exclude this item from purge on this page">Keep</th>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">SKU</th>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Deleted</th>
                        <th class="px-3 py-2 text-right font-medium">Warehouse qty</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($preview as $row)
                    <tr>
                        <td class="px-3 py-2">
                            <input type="checkbox"
                                   name="keep_ids[]"
                                   value="{{ $row['id'] }}"
                                   class="h-4 w-4 rounded border-gray-300"
                                   aria-label="Keep item #{{ $row['id'] }}"
                                   @change="syncKeep({{ $row['id'] }}, $event.target.checked)">
                            <input type="hidden" name="page_item_ids[]" value="{{ $row['id'] }}">
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            <a href="{{ route('items.show', $row['id']) }}" class="text-blue-600 hover:underline">#{{ $row['id'] }}</a>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            <a href="{{ route('items.show', $row['id']) }}" class="text-blue-600 hover:underline">{{ $row['code'] }}</a>
                        </td>
                        <td class="px-3 py-2">
                            <a href="{{ route('items.show', $row['id']) }}" class="text-blue-600 hover:underline">{{ $row['name'] }}</a>
                        </td>
                        <td class="px-3 py-2">{{ $row['type'] }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $row['deleted_at'] ? \Illuminate\Support\Carbon::parse($row['deleted_at'])->format('Y-m-d') : '—' }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $row['warehouse_qty'] }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No matching items for this preview.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $preview, 'label' => 'items'])

        <div class="border-t border-red-200 bg-red-50 p-4">
            <div class="text-sm font-semibold text-red-900">Execute purge (this page only)</div>
            <p class="mt-1 text-sm text-red-800">
                Purges only <strong>unchecked rows on page {{ $preview->currentPage() }}</strong>.
                Items on other pages are <strong>not</strong> affected — browse each page and submit separately.
            </p>
            <div class="mt-3 flex flex-wrap items-end gap-2">
                <div class="min-w-[16rem] flex-1">
                    <label class="mb-1 block text-xs font-medium text-red-800">Type PURGE-SELECTED-ITEMS to confirm</label>
                    <input type="text" name="confirm" required placeholder="PURGE-SELECTED-ITEMS" class="h-9 w-full max-w-md rounded-md border border-red-300 bg-white px-3 text-sm font-mono">
                </div>
                <button type="submit"
                        class="h-9 rounded-md bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="pagePurgeCount() === 0">
                    <span x-text="'Purge ' + pagePurgeCount().toLocaleString() + ' on this page'">Purge {{ number_format($pageItemCount) }} on this page</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
