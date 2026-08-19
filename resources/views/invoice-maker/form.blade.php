@extends('layouts.app')

@section('title', $invoice ? 'Edit Invoice' : 'New Invoice')

@section('content')
@php
$isEdit = (bool) $invoice;
$breadcrumbs = [
    ['title' => 'Invoice Maker', 'href' => route('invoice-maker.index')],
    ['title' => $isEdit ? 'Edit' : 'New', 'href' => $isEdit ? route('invoice-maker.edit', $invoice) : route('invoice-maker.create')],
];
$initialLines = old('lines', $invoice?->lines->map(fn ($line) => [
    'description' => $line->description,
    'quantity' => (float) $line->quantity,
    'price' => (float) $line->price,
])->values()->all() ?? [['description' => '', 'quantity' => 1, 'price' => 0]]);
$initialPresetId = old('preset_id', $invoice->preset_id ?? $selectedPreset['id']);
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="invoiceMakerForm(@js([
    'isEdit' => $isEdit,
    'number' => old('number', $invoice->number ?? \App\Models\StandaloneInvoice::generateNumber()),
    'date' => old('date', optional($invoice?->date)->format('Y-m-d') ?? now()->format('Y-m-d')),
    'recipient' => old('recipient', $invoice->recipient ?? ''),
    'sender_addrbook_id' => old('sender_addrbook_id', $invoice->sender_addrbook_id ?? ''),
    'preset_id' => $initialPresetId,
    'presets' => $presets,
    'notes' => old('notes', $invoice->notes ?? ''),
    'lines' => $initialLines,
    'senderInitial' => $invoice?->sender ? ['id' => $invoice->sender->id, 'name' => $invoice->sender->name] : null,
]))">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $isEdit ? 'Edit Invoice' : 'New Invoice' }}</h2>
        <p class="mt-0.5 text-sm text-gray-500">Design an invoice with free-text line items — no sell transaction required.</p>
    </div>

    <form method="POST" action="{{ $isEdit ? route('invoice-maker.update', $invoice) : route('invoice-maker.store') }}" @submit="prepareSubmit">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="preset_id" :value="form.preset_id">

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Header</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Invoice Number</label>
                            <input type="text" name="number" x-model="form.number" required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                            <input type="date" name="date" x-model="form.date" required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">From (Warehouse)</label>
                            <div x-data="asyncCombobox({
                                endpoint: @js($warehouseLookupUrl),
                                placeholder: 'Select warehouse for header...',
                                initial: @js($invoice?->sender ? ['id' => $invoice->sender->id, 'name' => $invoice->sender->name] : null),
                                onSelect: (item) => { form.sender_addrbook_id = item ? String(item.id) : ''; }
                            })" class="relative">
                                <input type="hidden" name="sender_addrbook_id" :value="form.sender_addrbook_id">
                                <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)"
                                       :placeholder="placeholder" autocomplete="off"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                                <div x-show="open" x-cloak @click.away="open = false" class="combobox-options" x-ref="optionsList">
                                    <template x-for="(item, idx) in items" :key="item.id">
                                        <div class="combobox-option" :class="{ 'active': idx === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = idx">
                                            <span x-text="item.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Kepada (Recipient) <span class="text-red-500">*</span></label>
                            <textarea name="recipient" x-model="form.recipient" rows="3" required
                                      placeholder="Recipient name and address"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"></textarea>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Line Items</h3>
                        <button type="button" @click="addLine()" class="text-sm font-medium text-blue-700 hover:text-blue-800">+ Add line</button>
                    </div>
                    <div class="hidden gap-2 px-1 pb-2 text-xs font-semibold uppercase text-gray-500 sm:grid sm:grid-cols-12">
                        <div class="sm:col-span-5">Item</div>
                        <div class="sm:col-span-2 text-right">Qty</div>
                        <div class="sm:col-span-2 text-right">Price</div>
                        <div class="sm:col-span-2 text-right">Total</div>
                        <div class="sm:col-span-1"></div>
                    </div>
                    <template x-for="(line, index) in form.lines" :key="index">
                        <div class="mb-2 grid gap-2 rounded-lg border border-gray-100 bg-gray-50 p-3 sm:grid-cols-12 sm:items-center">
                            <div class="sm:col-span-5">
                                <label class="mb-1 block text-xs text-gray-500 sm:hidden">Item</label>
                                <input type="text" :name="'lines['+index+'][description]'" x-model="line.description" required
                                       placeholder="Item description"
                                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs text-gray-500 sm:hidden">Qty</label>
                                <input type="number" step="0.0001" min="0.0001" :name="'lines['+index+'][quantity]'" x-model.number="line.quantity" @input="recalc()" required
                                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-right text-sm focus:border-blue-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs text-gray-500 sm:hidden">Price</label>
                                <input type="number" step="0.01" min="0" :name="'lines['+index+'][price]'" x-model.number="line.price" @input="recalc()" required
                                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-right text-sm focus:border-blue-500">
                            </div>
                            <div class="sm:col-span-2 text-right font-mono text-sm font-medium text-gray-900" x-text="formatCurrency(lineTotal(line))"></div>
                            <div class="sm:col-span-1 text-right">
                                <button type="button" @click="removeLine(index)" x-show="form.lines.length > 1"
                                        class="rounded-md p-1 text-red-500 hover:bg-red-50" title="Remove line">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>
                    <div class="mt-4 flex justify-end border-t border-gray-200 pt-3 text-sm">
                        <div class="space-y-1 text-right">
                            <div class="text-gray-500">Total Qty: <span class="font-mono font-medium text-gray-900" x-text="formatAmount(totalQty())"></span></div>
                            <div class="text-base font-semibold text-gray-900">Subtotal: <span class="font-mono" x-text="formatCurrency(grandTotal())"></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Payment & Signature</h3>
                        @if($can['settings'] ?? false)
                        <a href="{{ route('invoice-maker.settings.index') }}" class="text-xs font-medium text-blue-700 hover:underline">Manage presets</a>
                        @endif
                    </div>
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Preset</label>
                            <select x-model="form.preset_id" @change="applyPreset()" data-testid="invoice-preset-select"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                                <template x-for="preset in form.presets" :key="preset.id">
                                    <option :value="preset.id" x-text="preset.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="rounded-lg border border-gray-100 bg-gray-50 p-3 text-sm text-gray-700" x-show="selectedPreset()">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Preview</div>
                            <div class="space-y-2">
                                <div>
                                    <div class="text-xs text-gray-500">Template</div>
                                    <div class="font-medium" x-text="selectedPreset()?.template_label"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Terms of Payment</div>
                                    <ul class="mt-1 list-disc space-y-1 pl-4" x-show="selectedPreset()?.terms_lines?.length">
                                        <template x-for="line in selectedPreset()?.terms_lines || []" :key="line">
                                            <li x-text="line"></li>
                                        </template>
                                    </ul>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Pay To</div>
                                    <div class="whitespace-pre-line" x-text="selectedPreset()?.pay_to || '—'"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Signatory</div>
                                    <div class="font-medium" x-text="selectedPreset()?.signatory_name || '—'"></div>
                                    <img x-show="selectedPreset()?.signature_url" :src="selectedPreset()?.signature_url" alt="Signature preview" class="mt-2 h-12 w-auto object-contain">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="2" x-model="form.notes"
                                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button type="submit" :disabled="!canSubmit()" data-testid="save-invoice-button"
                            class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $isEdit ? 'Update Invoice' : 'Create Invoice' }}
                    </button>
                    <a href="{{ $isEdit ? route('invoice-maker.show', $invoice) : route('invoice-maker.index') }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-center text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function invoiceMakerForm(initial) {
    const templateLabels = @js(\App\Models\StandaloneInvoice::TEMPLATES);

    return {
        form: initial,
        init() {
            this.recalc();
            this.applyPreset();
        },
        selectedPreset() {
            const preset = this.form.presets.find((item) => item.id === this.form.preset_id);
            if (!preset) return null;

            return {
                ...preset,
                template_label: templateLabels[preset.template] || preset.template,
                terms_lines: String(preset.terms_of_payment || '')
                    .split(/\r?\n/)
                    .map((line) => line.trim())
                    .filter((line) => line !== ''),
            };
        },
        applyPreset() {},
        addLine() {
            this.form.lines.push({ description: '', quantity: 1, price: 0 });
        },
        removeLine(index) {
            if (this.form.lines.length <= 1) return;
            this.form.lines.splice(index, 1);
            this.recalc();
        },
        lineTotal(line) {
            return (Number(line.quantity) || 0) * (Number(line.price) || 0);
        },
        totalQty() {
            return this.form.lines.reduce((sum, line) => sum + (Number(line.quantity) || 0), 0);
        },
        grandTotal() {
            return this.form.lines.reduce((sum, line) => sum + this.lineTotal(line), 0);
        },
        recalc() {},
        formatAmount(value) {
            return formatAmountId(value);
        },
        formatCurrency(value) {
            return 'Rp ' + formatAmountId(value);
        },
        canSubmit() {
            if (!this.form.recipient?.trim()) return false;
            if (!this.form.preset_id) return false;
            if (!this.form.lines.length) return false;
            return this.form.lines.every(line =>
                line.description?.trim() &&
                Number(line.quantity) > 0 &&
                Number(line.price) >= 0
            );
        },
        prepareSubmit(event) {
            if (!this.canSubmit()) {
                event.preventDefault();
            }
        },
    };
}
</script>
@endpush
@endsection
