@php
    $typeAccount = (string) \App\Models\Addrbook::TYPE_ACCOUNT;
    $typeBank = (string) \App\Models\Addrbook::TYPE_BANK;
    $typeCustomer = (string) \App\Models\Addrbook::TYPE_CUSTOMER;
    $typeReseller = (string) \App\Models\Addrbook::TYPE_RESELLER;
    $typeSupplier = (string) \App\Models\Addrbook::TYPE_SUPPLIER;
    $isInternalLending = (bool) old('is_internal_lending', $isEdit && $addrbook ? $addrbook->is_internal_lending : false);
    $isActiveInReports = (bool) old('is_active_in_reports', $isEdit && $addrbook ? ($addrbook->is_active_in_reports ?? true) : true);
    $reportingRole = old('reporting_role', $addrbook?->reporting_role ?? '');
@endphp

<div class="rounded-xl border border-gray-200 bg-white shadow-sm md:col-span-2"
     x-show="selectedType === '{{ $typeAccount }}' || selectedType === '{{ $typeBank }}' || selectedType === '{{ $typeCustomer }}' || selectedType === '{{ $typeReseller }}' || selectedType === '{{ $typeSupplier }}'"
     x-cloak>
    <div class="border-b border-gray-100 px-5 py-4">
        <h3 class="text-sm font-semibold text-gray-900">Reporting</h3>
        <p class="text-xs text-gray-500">Revenue/tax entity comes from the <strong>bank on each Cash In</strong> (mapped under <a href="{{ route('reports.entities.index') }}" class="text-blue-600 hover:underline">Reporting Entities</a>), not a default on the customer.</p>
    </div>
    <div class="space-y-4 p-5">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="npwp" class="mb-1 block text-sm font-medium text-gray-700">NPWP</label>
                <input type="text" id="npwp" name="npwp" value="{{ $val('npwp') }}" placeholder="Optional"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
        </div>

        {{-- Ledger account --}}
        <div x-show="selectedType === '{{ $typeAccount }}'" class="space-y-4">
            <div>
                <label for="operation_id" class="mb-1 block text-sm font-medium text-gray-700">Category (Operation)</label>
                <select id="operation_id" name="operation_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">— Select category —</option>
                    @foreach($operations ?? [] as $op)
                        <option value="{{ $op->id }}" @selected((string) old('operation_id', $addrbook?->operation_id ?? '') === (string) $op->id)>{{ $op->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ledger_hint" class="mb-1 block text-sm font-medium text-gray-700">Cash entry hint</label>
                <textarea id="ledger_hint" name="ledger_hint" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                          placeholder="Shown when staff select this ledger on Cash In/Out">{{ old('ledger_hint', $addrbook?->ledger_hint ?? '') }}</textarea>
            </div>
        </div>

        {{-- Bank --}}
        <div x-show="selectedType === '{{ $typeBank }}'" class="space-y-3">
            @if($isEdit && isset($assignedEntity) && $assignedEntity)
                <p class="text-sm text-gray-600">Entity: <strong>{{ $assignedEntity->name }}</strong>
                    @if($assignedEntity->is_pkp)<span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-800">PKP</span>@else<span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs">Non-PKP</span>@endif
                </p>
            @elseif($isEdit)
                <p class="text-sm text-amber-700">Not assigned to a reporting entity yet.</p>
            @endif
            <div x-data="{ on: {{ $isActiveInReports ? 'true' : 'false' }} }" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Active in reports</p>
                    <p class="text-xs text-gray-500">Uncheck for Transfer Pending, Investment, etc.</p>
                </div>
                <input type="hidden" name="is_active_in_reports" :value="on ? 1 : 0">
                <button type="button" @click="on = !on" :class="on ? 'bg-blue-600' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full transition-colors">
                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 transform rounded-full bg-white transition-transform"></span>
                </button>
            </div>
        </div>

        {{-- Customer / Reseller --}}
        <div x-show="selectedType === '{{ $typeCustomer }}' || selectedType === '{{ $typeReseller }}'" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="payment_due_day" class="mb-1 block text-sm font-medium text-gray-700">Payment due day</label>
                    <input type="number" id="payment_due_day" name="payment_due_day" min="1" max="31"
                           value="{{ old('payment_due_day', $addrbook?->payment_due_day ?? '') }}"
                           placeholder="e.g. 15 for MDS, 6 for Central"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Day of month counterparty usually pays (month after faktur).</p>
                </div>
                <div>
                    <label for="payment_grace_days" class="mb-1 block text-sm font-medium text-gray-700">Payment grace days</label>
                    <input type="number" id="payment_grace_days" name="payment_grace_days" min="0" max="60"
                           value="{{ old('payment_grace_days', $addrbook?->payment_grace_days ?? 7) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-gray-500">Overdue flag after expected date + grace (default 7).</p>
                </div>
            </div>
            <div x-data="{ on: {{ $isInternalLending ? 'true' : 'false' }} }" class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div>
                    <p class="text-sm font-medium text-gray-900">Internal lending</p>
                    <p class="text-xs text-gray-500">Investment / pinjaman to customer — exclude from sales tax totals.</p>
                </div>
                <input type="hidden" name="is_internal_lending" :value="on ? 1 : 0">
                <button type="button" @click="on = !on" :class="on ? 'bg-blue-600' : 'bg-gray-300'" class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full transition-colors">
                    <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 transform rounded-full bg-white transition-transform"></span>
                </button>
            </div>
        </div>

        {{-- Supplier --}}
        <div x-show="selectedType === '{{ $typeSupplier }}'">
            <label for="reporting_role" class="mb-1 block text-sm font-medium text-gray-700">Material supplier</label>
            <select id="reporting_role" name="reporting_role" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="" @selected($reportingRole === '')>General supplier</option>
                <option value="material" @selected($reportingRole === 'material')>Material / fabric supplier</option>
            </select>
        </div>
    </div>
</div>
