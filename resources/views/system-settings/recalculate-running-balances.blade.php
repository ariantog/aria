@extends('layouts.app')

@section('title', 'Recalculate Running Balances')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Recalculate Running Balances', 'href' => route('recalculate-running-balances.index')],
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
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">Recalculate Running Balances</h1>
        <p class="text-gray-500">
            Rebuilds <code class="rounded bg-gray-100 px-1 text-xs">sender_balance</code> and
            <code class="rounded bg-gray-100 px-1 text-xs">receiver_balance</code> in date+id order,
            then syncs addrbook stats. Same as
            <code class="rounded bg-gray-100 px-1 text-xs">app:recalculate-running-balances</code>.
            Does not change signed <code class="rounded bg-gray-100 px-1 text-xs">total</code> or stock.
        </p>
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

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Transactions</h2>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ number_format($coverage['transactions']) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Earliest date</h2>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $coverage['earliest'] ?? '—' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Latest date</h2>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ $coverage['latest'] ?? '—' }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        @if($canRun)
        <form method="POST"
              action="{{ route('recalculate-running-balances.run') }}"
              id="recalculate-running-balances-form"
              data-testid="recalculate-running-balances-form"
              class="space-y-4"
              @submit="if (!markSubmitting()) { $event.preventDefault() }">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="recalculate-running-balances-from" class="mb-1 block text-sm font-medium text-gray-700">From date (optional)</label>
                    <input id="recalculate-running-balances-from"
                           data-testid="recalculate-running-balances-from"
                           name="from"
                           type="date"
                           value="{{ old('from') }}"
                           class="h-9 w-full rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900">
                    <p class="mt-1 text-xs text-gray-500">Leave empty to rebuild the full history. Opening balances before this date are kept.</p>
                    @error('from')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div x-data="asyncCombobox({
                        endpoint: @js($lookupUrl),
                        placeholder: 'All contacts…',
                        initial: @js($addrbookInitial),
                    })" x-init="init()" class="relative">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Addrbook (optional)</label>
                    <input type="hidden" name="addrbook_id" :value="selected ? selected.id : ''">
                    <input type="text"
                           id="recalculate-running-balances-addrbook"
                           data-testid="recalculate-running-balances-addrbook"
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
                    <p class="mt-1 text-xs text-gray-500">Leave empty to rebuild every contact that posts a money balance.</p>
                    @error('addrbook_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <label class="flex items-start gap-2 text-sm text-gray-700">
                <input type="checkbox"
                       id="recalculate-running-balances-confirm"
                       data-testid="recalculate-running-balances-confirm"
                       name="confirm"
                       value="1"
                       required
                       class="mt-0.5 rounded border-gray-300"
                       x-model="confirmed">
                <span>I understand this rewrites stored running balances on matching transactions.</span>
            </label>

            <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit"
                        id="recalculate-running-balances-submit"
                        data-testid="recalculate-running-balances-submit"
                        :disabled="!canSubmit()"
                        :class="canSubmit() ? 'bg-blue-600 hover:bg-blue-700' : 'cursor-not-allowed bg-gray-300'"
                        class="h-9 rounded-md px-4 text-sm font-medium text-white">
                    Recalculate balances
                </button>
                <p class="text-xs text-gray-500">Large ledgers can take a while. This request waits until the rebuild finishes.</p>
            </div>
        </form>
        @else
        <p class="text-sm text-gray-600">You can view this tool, but recalculating balances needs the system settings edit permission.</p>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-600 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold text-gray-900">What this rebuilds</h2>
        <ul class="list-inside list-disc space-y-1">
            <li>Walks completed ledger rows in date then id order and re-derives each contact’s running balance from signed <code class="rounded bg-gray-100 px-1 text-xs">total</code>.</li>
            <li>Writes the result onto <code class="rounded bg-gray-100 px-1 text-xs">sender_balance</code> / <code class="rounded bg-gray-100 px-1 text-xs">receiver_balance</code> and syncs <code class="rounded bg-gray-100 px-1 text-xs">customerstat</code>.</li>
            <li>A from-date keeps balances before that day and only rewrites that day onward.</li>
            <li>An addrbook filter only rewrites that contact’s side of each row.</li>
        </ul>
    </div>
</div>
@endsection
