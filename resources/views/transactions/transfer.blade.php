@extends('layouts.app')

@section('title', 'Transfer Money')

@section('content')
<div class="flex flex-col gap-4 p-4 sm:p-6">

    <div class="flex items-center gap-3">
        <a href="{{ route('transactions.index') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50">
            <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Transfer Money</h2>
            <p class="mt-0.5 text-sm text-gray-500">Transfer balances between bank and virtual accounts.</p>
        </div>
    </div>

    @if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="POST" action="{{ route('transactions.transfer.store') }}" x-data="transferForm()" x-init="init()" @submit="guardFormSubmit($event)" class="space-y-5">
        @csrf

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    Transfer Details
                </h3>
            </div>
            <div class="space-y-5 p-5">
                <div>
                    <label for="date" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="date" name="date"
                           min="{{ $min_date ?? '' }}"
                           value="{{ old('date', \Carbon\Carbon::today()->toDateString()) }}"
                           class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('date') border-red-500 @else border-gray-300 @enderror">
                    @error('date')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="invoice" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Invoice Number
                    </label>
                    <input type="text" id="invoice" name="invoice" value="{{ old('invoice') }}" placeholder="Optional"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @error('invoice')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="sender" class="mb-1 block text-sm font-medium text-gray-700">From Account <span class="text-red-500">*</span></label>
                    <select id="sender" name="sender"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('sender') border-red-500 @else border-gray-300 @enderror">
                        <option value="">Choose source account…</option>
                        @foreach($bankList as $bank)
                        <option value="{{ $bank->id }}" @selected(old('sender', $defaultAccounts['sender_id'] ?? null) == $bank->id)>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    @error('sender')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="receiver" class="mb-1 block text-sm font-medium text-gray-700">To Account <span class="text-red-500">*</span></label>
                    <select id="receiver" name="receiver"
                            class="w-full rounded-lg border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('receiver') border-red-500 @else border-gray-300 @enderror">
                        <option value="">Choose destination account…</option>
                        @foreach($bankList as $bank)
                        <option value="{{ $bank->id }}" @selected(old('receiver', $defaultAccounts['receiver_id'] ?? null) == $bank->id)>{{ $bank->name }}</option>
                        @endforeach
                    </select>
                    @error('receiver')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4">
                <h3 class="text-sm font-semibold text-gray-900">Amount &amp; Note</h3>
            </div>
            <div class="space-y-5 p-5">
                <div>
                    <label for="total" class="mb-1 block text-sm font-medium text-gray-700">Total Amount <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-500">Rp</span>
                        <input type="number" id="total" name="total" x-model.number="totalAmount" min="0.01" step="any" inputmode="decimal"
                               placeholder="0"
                               class="w-full rounded-lg border px-3 py-2 pl-10 text-right text-lg font-semibold tabular-nums focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('total') border-red-500 @else border-gray-300 @enderror">
                    </div>
                    <p x-show="totalAmount > 0" x-cloak class="mt-1 text-right text-sm tabular-nums text-gray-600"
                       x-text="'Rp ' + formatAmountId(totalAmount)"></p>
                    @error('total')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="description" class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Note
                    </label>
                    <textarea id="description" name="description" rows="3" placeholder="Add transaction details…"
                              class="min-h-[5.5rem] w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="flex flex-col gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-sm font-semibold text-gray-900">Grand Total</span>
                <span class="text-lg font-bold tabular-nums text-blue-700 sm:text-right"
                      x-text="totalAmount > 0 ? 'Rp ' + formatAmountId(totalAmount) : '—'"></span>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end sm:gap-3">
            <p class="text-center text-xs text-gray-500 sm:mr-auto sm:text-left">Transfer between internal bank and virtual accounts.</p>
            <a href="{{ route('transactions.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500">
                <span x-show="!submitting">Save Transfer</span>
                <span x-show="submitting" x-cloak>Saving…</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
const _TransferOldTotal = @js(old('total'));

function transferForm() {
    return {
        ...formSubmitGuard(),
        totalAmount: _TransferOldTotal != null && _TransferOldTotal !== '' ? Number(_TransferOldTotal) : null,
        init() {
            if (this.totalAmount != null && Number.isNaN(this.totalAmount)) {
                this.totalAmount = null;
            }
        },
    };
}
</script>
@endpush
@endsection
