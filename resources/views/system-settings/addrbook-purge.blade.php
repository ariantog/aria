@extends('layouts.app')

@section('title', 'Delete Addrbook')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'Data Retention', 'href' => route('data-retention.index')],
    ['title' => 'Delete Addrbook', 'href' => route('data-retention.addrbook-purge.index')],
];
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
                Permanently remove a contact, warehouse, bank, ledger account, or other addrbook row.
                Only addrbooks with <strong>no rows in <code class="rounded bg-gray-100 px-1 text-xs">transactions</code></strong>
                (as sender or receiver) can be deleted.
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
            <p class="mt-1 text-xs text-gray-500">Pick a row, then click Preview to check whether it can be deleted.</p>
        </div>

        <div class="mt-4">
            <button type="submit"
                    data-testid="addrbook-purge-preview"
                    class="h-9 rounded-md bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">
                Preview
            </button>
        </div>
    </form>

    @if($preview !== null)
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">Preview</h2>
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
@endsection
