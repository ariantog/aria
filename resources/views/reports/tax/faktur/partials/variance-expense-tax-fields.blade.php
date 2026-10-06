@php
    $selectId = $selectId ?? 'variance_expense_addrbook_id';
    $expenseValue = $expenseValue ?? old('variance_expense_addrbook_id', $import->variance_expense_addrbook_id);
@endphp
<div class="space-y-2">
    <div>
        <label class="mb-1 block text-xs text-gray-500" for="{{ $selectId }}">{{ $label ?? 'Akun biaya selisih (opsional)' }}</label>
        <select id="{{ $selectId }}" name="variance_expense_addrbook_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                @change="syncTotalFromBankAndSelisih(); syncTaxFromVariance()">
            <option value="">— Tidak ada —</option>
            @foreach($expenseAccounts as $account)
                <option value="{{ $account->id }}" @selected((int) $expenseValue === (int) $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
    </div>

    @if($varianceEntityIsPkp ?? false)
        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50/80 px-3 py-2.5" x-show="varianceTaxBase() >= 0.01" x-cloak>
            <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                <input type="hidden" name="variance_record_ppn" value="0">
                <input type="checkbox"
                       name="variance_record_ppn"
                       value="1"
                       x-model.boolean="tax.record_ppn"
                       @change="onRecordPpnToggle()"
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                       data-testid="faktur-variance-record-ppn">
                Record PPN masukan (selisih)
            </label>
            <div x-show="tax.record_ppn" x-cloak class="mt-2 space-y-2">
                <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                    <input type="hidden" name="variance_record_pph" value="0">
                    <input type="checkbox"
                           name="variance_record_pph"
                           value="1"
                           x-model.boolean="tax.record_pph"
                           @change="onRecordPphToggle()"
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                           data-testid="faktur-variance-record-pph">
                    Include PPh withholding (<span x-text="pphRate"></span>% of DPP)
                </label>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">DPP (Rp)</label>
                        <input type="number"
                               name="variance_ppn_dpp"
                               x-model.number="tax.ppn_dpp"
                               min="0"
                               step="any"
                               @input="markPpnManual()"
                               :disabled="!tax.record_ppn"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div>
                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">PPN (Rp)</label>
                        <input type="number"
                               name="variance_ppn"
                               x-model.number="tax.ppn"
                               min="0"
                               step="any"
                               @input="markPpnManual()"
                               :disabled="!tax.record_ppn"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums">
                    </div>
                    <div x-show="tax.record_pph" x-cloak>
                        <label class="mb-1 block text-[10px] font-medium uppercase tracking-wide text-gray-500">PPh (Rp)</label>
                        <input type="number"
                               name="variance_pph"
                               x-model.number="tax.pph"
                               min="0"
                               step="any"
                               @input="markPpnManual()"
                               :disabled="!tax.record_ppn || !tax.record_pph"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm tabular-nums">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500">
                    DPP, PPN<span x-show="tax.record_pph">, dan PPh</span> dihitung dari nominal selisih. Sesuaikan jika berbeda dari faktur biaya.
                </p>
            </div>
        </div>
    @endif
</div>
