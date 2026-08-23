@php
    $perPage = $perPage ?? (int) request()->query('per_page', 100);
    $selectedType = (string) ($selectedType ?? ($filters['type'] ?? ''));
    $showPartyFilters = $showPartyFilters ?? false;
@endphp

<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">From</label>
    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">To</label>
    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Type</label>
    <select name="type" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        @foreach($typeOptions as $typeId => $label)
            <option value="{{ $typeId }}" @selected($selectedType == (string) $typeId)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Invoice</label>
    <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Invoice…"
           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Item ID</label>
    <input type="number" name="item_id" value="{{ $filters['item_id'] ?? '' }}" placeholder="Barcode / ID"
           class="w-28 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Item Code</label>
    <input type="text" name="item_code" value="{{ $filters['item_code'] ?? '' }}" placeholder="SKU…"
           class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Qty min</label>
    <input type="number" step="0.01" name="qty_min" value="{{ $filters['qty_min'] ?? '' }}"
           class="w-24 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Qty max</label>
    <input type="number" step="0.01" name="qty_max" value="{{ $filters['qty_max'] ?? '' }}"
           class="w-24 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
</div>
@if($showPartyFilters)
    @include('transactions.partials.export-sell-party-combobox', [
        'name' => 'sender',
        'label' => $senderLabel ?? 'Sender',
        'placeholder' => 'Select '.($senderLabel ?? 'sender').'...',
        'endpoint' => $senderLookupUrl ?? '',
        'initial' => $selectedSender ?? null,
    ])
    @include('transactions.partials.export-sell-party-combobox', [
        'name' => 'receiver',
        'label' => $receiverLabel ?? 'Receiver',
        'placeholder' => 'Select '.($receiverLabel ?? 'receiver').'...',
        'endpoint' => $receiverLookupUrl ?? '',
        'initial' => $selectedReceiver ?? null,
    ])
@endif
<div class="flex flex-col gap-1">
    <label class="text-xs font-medium uppercase text-gray-500">Rows / page</label>
    <select name="per_page" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        @foreach([100, 200, 300] as $size)
            <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }}</option>
        @endforeach
    </select>
</div>
<div class="flex gap-2">
    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
    <a href="{{ $resetUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
</div>
