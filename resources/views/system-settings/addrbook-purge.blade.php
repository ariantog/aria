@extends('layouts.app')

@section('title', 'Delete Addrbook')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Data Retention', 'href' => route('data-retention.index')],
    ['title' => 'Delete Addrbook', 'href' => route('data-retention.addrbook-purge.index')],
];
$pageAddrbookIds = $list ? collect($list->items())->pluck('id')->values()->all() : [];
$pageAddrbookCount = $list ? $list->count() : 0;
@endphp

<div class="flex flex-col gap-4 p-4"
     x-data="{
        confirmed: false,
        submitting: false,
        canSubmit() {
            return this.confirmed && !this.submitting;
        },
        markSubmitting() {
            if (!this.canSubmit()) {
                return false;
            }
            this.submitting = true;
            return true;
        }
     }">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Delete Addrbook</h1>
            <p class="text-sm text-gray-500">
                Permanently remove contacts, warehouses, banks, ledger accounts, and other addrbook rows
                that never appeared in <code class="rounded bg-gray-100 px-1 text-xs">transactions</code>
                as sender or receiver.
            </p>
            <p class="mt-1 text-xs text-gray-500">Superadmin only. This cannot be undone.</p>
        </div>
        <a href="{{ route('data-retention.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← Data Retention</a>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif

    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="GET"
          action="{{ route('data-retention.addrbook-purge.index') }}"
          class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">Browse by type</h2>
        <p class="mt-1 text-xs text-gray-500">Pick an addrbook type, then list every row with no transactions.</p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div>
                <label for="addrbook-purge-type" class="mb-1 block text-xs font-medium text-gray-500">Addrbook type</label>
                <select id="addrbook-purge-type"
                        name="type"
                        data-testid="addrbook-purge-type"
                        required
                        class="h-9 min-w-[12rem] rounded-md border border-gray-300 px-3 text-sm">
                    <option value="">Select type…</option>
                    @foreach($addrbookTypes as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    data-testid="addrbook-purge-list"
                    class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                List eligible
            </button>
        </div>
    </form>

    @if($selectedType !== null && $list !== null)
    <form method="POST"
          action="{{ route('data-retention.addrbook-purge.purge') }}"
          class="rounded-xl border border-gray-200 bg-white shadow-sm"
          x-data="{
              pageAddrbookIds: @js($pageAddrbookIds),
              keepIds: [],
              pagePurgeCount() {
                  return this.pageAddrbookIds.filter((id) => ! this.keepIds.includes(id)).length;
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
          @submit="if (! confirm('Permanently delete ' + pagePurgeCount() + ' addrbook(s) on this page?')) { $event.preventDefault(); }">
        @csrf
        <input type="hidden" name="type" value="{{ $selectedType }}">
        <input type="hidden" name="page" value="{{ $list->currentPage() }}">

        <div class="flex flex-wrap items-center justify-between gap-2 p-4 pb-0">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">{{ $selectedTypeLabel }}</h2>
                <p class="text-xs text-gray-500">Only rows with no transactions are listed.</p>
            </div>
            <div class="text-sm text-gray-500">
                <span>{{ number_format($list->total()) }} eligible total</span>
                <span class="mx-1">·</span>
                <span>page {{ $list->currentPage() }} of {{ max(1, $list->lastPage()) }}</span>
                <span class="mx-1">·</span>
                <span>{{ number_format($pageAddrbookCount) }} on this page</span>
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm" data-testid="addrbook-purge-list-table">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-3 py-2 font-medium" title="Check to exclude this row from deletion on this page">Keep</th>
                        <th class="px-3 py-2 font-medium">ID</th>
                        <th class="px-3 py-2 font-medium">Name</th>
                        <th class="px-3 py-2 font-medium">Member ID</th>
                        <th class="px-3 py-2 font-medium">Soft deleted</th>
                        <th class="px-3 py-2 font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($list as $row)
                    <tr>
                        <td class="px-3 py-2">
                            <input type="checkbox"
                                   name="keep_ids[]"
                                   value="{{ $row['id'] }}"
                                   class="h-4 w-4 rounded border-gray-300"
                                   aria-label="Keep addrbook {{ $row['id'] }}"
                                   @change="syncKeep({{ $row['id'] }}, $event.target.checked)">
                            <input type="hidden" name="page_addrbook_ids[]" value="{{ $row['id'] }}">
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $row['id'] }}</td>
                        <td class="px-3 py-2">{{ $row['name'] }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $row['member_id'] ?: '—' }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $row['deleted_at'] ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('data-retention.addrbook-purge.index', ['type' => $selectedType, 'page' => $list->currentPage(), 'addrbook_id' => $row['id']]) }}"
                               class="text-blue-600 hover:underline">
                                Preview
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-gray-500" data-testid="addrbook-purge-empty">
                            No eligible {{ strtolower($selectedTypeLabel) }} rows found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('partials.pagination', ['paginator' => $list, 'label' => 'addrbooks'])

        @if($pageAddrbookCount > 0)
        <div class="border-t border-red-200 bg-red-50 p-4">
            <div class="text-sm font-semibold text-red-900">Delete selected (this page only)</div>
            <p class="mt-1 text-sm text-red-800">
                Deletes only <strong>unchecked rows on page {{ $list->currentPage() }}</strong>.
                Other pages are not affected.
            </p>
            <div class="mt-3 flex flex-wrap items-end gap-2">
                <div class="min-w-[16rem] flex-1">
                    <label class="mb-1 block text-xs font-medium text-red-800">Type DELETE-ADDRBOOK to confirm</label>
                    <input type="text"
                           name="confirm"
                           required
                           placeholder="DELETE-ADDRBOOK"
                           data-testid="addrbook-purge-bulk-confirm"
                           class="h-9 w-full max-w-md rounded-md border border-red-300 bg-white px-3 text-sm font-mono">
                </div>
                <button type="submit"
                        data-testid="addrbook-purge-bulk-submit"
                        class="h-9 rounded-md bg-red-600 px-4 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="pagePurgeCount() === 0">
                    <span x-text="'Delete ' + pagePurgeCount().toLocaleString() + ' on this page'">Delete {{ number_format($pageAddrbookCount) }} on this page</span>
                </button>
            </div>
        </div>
        @endif
    </form>
    @endif

    <details class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <summary class="cursor-pointer text-sm font-semibold text-gray-900">Look up one addrbook</summary>
        <div class="mt-4 space-y-4">
            <form method="GET"
                  action="{{ route('data-retention.addrbook-purge.index') }}"
                  class="space-y-4">
                @if($selectedType !== null)
                <input type="hidden" name="type" value="{{ $selectedType }}">
                @if($list)
                <input type="hidden" name="page" value="{{ $list->currentPage() }}">
                @endif
                @endif

                <div x-data="asyncCombobox({
                        endpoint: @js($lookupUrl),
                        placeholder: 'Search by name, member id, or numeric id…',
                        initial: @js($addrbookInitial),
                    })" x-init="init()" class="relative max-w-xl">
                    <label for="addrbook-purge-lookup" class="mb-1 block text-sm font-medium text-gray-700">Addrbook</label>
                    <input type="hidden" name="addrbook_id" :value="selected ? selected.id : ''">
                    <input type="text"
                           id="addrbook-purge-lookup"
                           data-testid="addrbook-purge-lookup"
                           x-model="query"
                           @input="handleInput()"
                           @focus="handleFocus()"
                           @keydown="handleKeydown($event)"
                           :placeholder="placeholder"
                           autocomplete="off"
                           class="h-9 w-full rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900">
                    <div x-show="open" @click.away="open = false" x-cloak class="combobox-options" x-ref="optionsList">
                        <template x-for="(item, idx) in items" :key="item.id">
                            <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                                <span x-text="item.name"></span>
                            </div>
                        </template>
                        <div x-show="!loading && items.length === 0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                    </div>
                </div>

                <button type="submit"
                        data-testid="addrbook-purge-preview"
                        class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                    Preview
                </button>
            </form>

            @if($preview !== null)
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <h3 class="text-sm font-semibold text-gray-900">Preview</h3>
                <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">ID</dt>
                        <dd class="mt-0.5 font-medium text-gray-900" data-testid="addrbook-purge-preview-id">{{ $preview['id'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Type</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $preview['type_label'] }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Name</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $preview['name'] }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Transactions</dt>
                        <dd class="mt-0.5">
                            @if($preview['has_transactions'])
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700" data-testid="addrbook-purge-not-deletable">
                                Present in transactions — cannot delete
                            </span>
                            @else
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" data-testid="addrbook-purge-deletable">
                                No transactions — eligible for deletion
                            </span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if($preview['deletable'])
                <form method="POST"
                      action="{{ route('data-retention.addrbook-purge.destroy') }}"
                      id="addrbook-purge-form"
                      data-testid="addrbook-purge-form"
                      class="mt-4 space-y-4 border-t border-gray-200 pt-4"
                      @submit="if (!markSubmitting() || ! confirm('Permanently delete {{ addslashes($preview['name']) }} ({{ $preview['type_label'] }} #{{ $preview['id'] }})?')) { $event.preventDefault(); submitting = false; }">
                    @csrf
                    <input type="hidden" name="addrbook_id" value="{{ $preview['id'] }}">
                    @if($selectedType !== null)
                    <input type="hidden" name="type" value="{{ $selectedType }}">
                    @if($list)
                    <input type="hidden" name="page" value="{{ $list->currentPage() }}">
                    @endif
                    @endif

                    <div>
                        <label for="addrbook-purge-confirm-text" class="mb-1 block text-sm font-medium text-gray-700">
                            Type <code class="rounded bg-gray-100 px-1 text-xs">DELETE-ADDRBOOK</code> to confirm
                        </label>
                        <input type="text"
                               id="addrbook-purge-confirm-text"
                               data-testid="addrbook-purge-confirm-text"
                               name="confirm"
                               required
                               autocomplete="off"
                               class="h-9 w-full max-w-xs rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900">
                    </div>

                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox"
                               id="addrbook-purge-confirm"
                               data-testid="addrbook-purge-confirm"
                               required
                               class="mt-0.5 rounded border-gray-300"
                               x-model="confirmed">
                        <span>I understand this permanently deletes the addrbook and related pivot/stat rows.</span>
                    </label>

                    <button type="submit"
                            id="addrbook-purge-submit"
                            data-testid="addrbook-purge-submit"
                            :disabled="!canSubmit()"
                            :class="canSubmit() ? 'bg-red-600 hover:bg-red-700' : 'cursor-not-allowed bg-gray-300'"
                            class="h-9 rounded-md px-4 text-sm font-medium text-white">
                        Delete addrbook
                    </button>
                </form>
                @endif
            </div>
            @endif
        </div>
    </details>
</div>
@endsection
