@extends('layouts.app')

@section('title', 'New Adjust')

@section('content')
<div class="flex flex-col gap-4 p-4" x-data="adjustForm()" x-init="init()">

    <div class="flex items-center gap-3">
        <a href="{{ route('transactions.index') }}" class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">New Adjust</h2>
            <p class="mt-0.5 text-sm text-gray-500">Direct balance adjustment between accounts and contacts.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="max-w-4xl rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('transactions.adjust.store') }}" @submit="guardFormSubmit($event)" class="max-w-4xl space-y-5">
        @csrf

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="p-6 grid grid-cols-1 gap-8 md:grid-cols-2">

                {{-- Left: General info --}}
                <div class="space-y-5">
                    <div class="space-y-1.5">
                        <label for="date" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Date
                        </label>
                        <input type="date" id="date" name="date"
                               min="{{ $min_date ?? '' }}"
                               value="{{ old('date', \Carbon\Carbon::today()->toDateString()) }}"
                               class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('date') border-red-500 @else border-gray-300 @enderror">
                        @error('date')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-1.5">
                        <label for="invoice" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Invoice Number
                        </label>
                        <input type="text" id="invoice" name="invoice" value="{{ old('invoice') }}" placeholder="Optional"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('invoice')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Right: Entities --}}
                <div class="space-y-5">
                    {{-- Receiver: balance increases when amount is positive --}}
                    <div class="space-y-1.5">
                        <label class="flex flex-col gap-0.5 text-sm font-semibold text-emerald-600">
                            <span>Receiver (credit)</span>
                            <span class="text-xs font-normal text-emerald-600/80">Balance increases by the amount</span>
                        </label>
                        <input type="hidden" name="receiver" :value="receiverId">
                        <div x-data="asyncCombobox({
                            endpoint: '{{ route('transactions.lookup', ['type' => 'adjust', 'role' => 'receiver']) }}',
                            placeholder: 'Search Account / Contact…',
                            onSelect: (item) => {
                                receiverId = item ? String(item.id) : '';
                                receiverBalance = item ? Number(item.balance || 0) : null;
                            }
                        })" class="relative">
                            <div class="relative flex h-10 overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 @error('receiver') border-red-500 @else border-gray-300 @enderror">
                                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                                       :placeholder="placeholder" class="flex-1 border-none bg-transparent px-3 text-sm outline-none" autocomplete="off">
                                <span x-show="loading" class="flex items-center pr-2"><svg class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                                <button type="button" @click="open=!open;if(!items.length)doSearch(query)" class="pr-2 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                </button>
                            </div>
                            <div x-show="open" @click.away="open=false" class="combobox-options" x-ref="optionsList">
                                <div x-show="!loading && items.length===0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)" @mouseenter="activeIndex=idx" class="combobox-option" :class="{'active':activeIndex===idx}">
                                        <span x-text="item.name"></span>
                                        <span class="ml-auto text-xs opacity-60" x-text="item.balance !== undefined ? 'Rp '+Number(item.balance).toLocaleString('id-ID') : ''"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="receiverBalance !== null"
                             class="flex items-center gap-2 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-1.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Current Balance</span>
                            <span class="font-mono text-sm font-bold text-emerald-700" x-text="'Rp ' + Number(receiverBalance||0).toLocaleString('id-ID')"></span>
                        </div>
                        @error('receiver')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>

                    {{-- Sender: balance decreases when amount is positive --}}
                    <div class="space-y-1.5">
                        <label class="flex flex-col gap-0.5 text-sm font-semibold text-rose-600">
                            <span>Sender (debit)</span>
                            <span class="text-xs font-normal text-rose-600/80">Balance decreases by the amount</span>
                        </label>
                        <input type="hidden" name="sender" :value="senderId">
                        <div x-data="asyncCombobox({
                            endpoint: '{{ route('transactions.lookup', ['type' => 'adjust', 'role' => 'sender']) }}',
                            placeholder: 'Search Account / Contact…',
                            onSelect: (item) => {
                                senderId = item ? String(item.id) : '';
                                senderBalance = item ? Number(item.balance || 0) : null;
                            }
                        })" class="relative">
                            <div class="relative flex h-10 overflow-hidden rounded-lg border focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 @error('sender') border-red-500 @else border-gray-300 @enderror">
                                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                                       :placeholder="placeholder" class="flex-1 border-none bg-transparent px-3 text-sm outline-none" autocomplete="off">
                                <span x-show="loading" class="flex items-center pr-2"><svg class="h-4 w-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                                <button type="button" @click="open=!open;if(!items.length)doSearch(query)" class="pr-2 text-gray-400">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                                </button>
                            </div>
                            <div x-show="open" @click.away="open=false" class="combobox-options" x-ref="optionsList">
                                <div x-show="!loading && items.length===0" class="px-3 py-2 text-sm text-gray-400" x-text="emptyMessage()"></div>
                                <template x-for="(item, idx) in items" :key="item.id">
                                    <div @click="selectItem(item)" @mouseenter="activeIndex=idx" class="combobox-option" :class="{'active':activeIndex===idx}">
                                        <span x-text="item.name"></span>
                                        <span class="ml-auto text-xs opacity-60" x-text="item.balance !== undefined ? 'Rp '+Number(item.balance).toLocaleString('id-ID') : ''"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div x-show="senderBalance !== null"
                             class="flex items-center gap-2 rounded-lg border border-rose-100 bg-rose-50 px-3 py-1.5">
                            <span class="text-xs font-bold uppercase tracking-wider text-rose-600">Current Balance</span>
                            <span class="font-mono text-sm font-bold text-rose-700" x-text="'Rp ' + Number(senderBalance||0).toLocaleString('id-ID')"></span>
                        </div>
                        @error('sender')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- Bottom: note + amount --}}
            <div class="border-t border-gray-100 p-6 grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="space-y-1.5 md:col-span-2">
                    <label for="description" class="flex items-center gap-2 text-sm font-medium text-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Note
                    </label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Brief explanation for this adjustment…"
                              class="w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ old('description') }}</textarea>
                </div>
                <div class="space-y-1.5">
                    <label for="total" class="text-sm font-medium text-gray-700">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-500">Rp</span>
                        <input type="number" id="total" name="total" value="{{ old('total') }}" min="0.01" step="any" placeholder=""
                               class="w-full rounded-lg border px-3 py-2 pl-10 text-right text-lg font-bold focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('total') border-red-500 @else border-gray-300 @enderror">
                    </div>
                    <p class="text-xs text-gray-500">Enter a positive amount. To reverse direction, swap sender and receiver.</p>
                    @error('total')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-col items-center justify-between gap-4 rounded-b-xl border-t border-gray-100 bg-gray-50 p-5 sm:flex-row">
                <div class="flex max-w-md items-start gap-3 text-sm text-gray-500">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span>At least one side must be a <b>Journal Account</b>. Adjustments between two Journal Accounts or Warehouse entities are not permitted.</span>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('transactions.index') }}"
                       class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
                    <button type="submit"
                            :disabled="submitting"
                            class="h-12 rounded-lg bg-indigo-600 px-10 text-sm font-bold text-white shadow-md hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span x-show="!submitting">Save Adjust</span>
                        <span x-show="submitting" x-cloak>Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function adjustForm() {
    return {
        ...formSubmitGuard(),
        senderId: '',
        receiverId: '',
        senderBalance: null,
        receiverBalance: null,
        init() {}
    };
}
</script>
@endpush
@endsection
