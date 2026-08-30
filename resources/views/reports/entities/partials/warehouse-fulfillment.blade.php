<div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm" data-testid="entities-fulfillment">
    <h3 class="text-sm font-semibold text-gray-900">Warehouse fulfillment</h3>
    <p class="mt-1 text-xs text-gray-500">Maps a warehouse (e.g. WTC / Citos) to the marketplace or customer channel it ships for. Analysis only — does not change CashOut ledgers or tax entity.</p>

    <form method="POST" action="{{ route('reports.entities.fulfillment.store') }}" class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end">
        @csrf
        <div class="flex-1">
            <label class="mb-1 block text-xs text-gray-500" for="fulfillment-warehouse">Warehouse</label>
            <select id="fulfillment-warehouse" name="warehouse_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="fulfillment-warehouse">
                <option value="">— Select warehouse —</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1">
            <label class="mb-1 block text-xs text-gray-500" for="fulfillment-customer">Customer / channel</label>
            <select id="fulfillment-customer" name="customer_id" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" data-testid="fulfillment-customer">
                <option value="">— Select customer —</option>
                @foreach($fulfillmentCustomers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1">
            <label class="mb-1 block text-xs text-gray-500" for="fulfillment-notes">Notes</label>
            <input id="fulfillment-notes" type="text" name="notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Optional">
        </div>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" data-testid="fulfillment-save">Attach</button>
    </form>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-gray-50 text-left text-xs text-gray-500">
                    <th class="px-3 py-2 font-medium">Warehouse</th>
                    <th class="px-3 py-2 font-medium">Customer / channel</th>
                    <th class="px-3 py-2 font-medium">Notes</th>
                    <th class="px-3 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fulfillments as $row)
                    <tr class="border-b">
                        <td class="px-3 py-2">{{ $row->warehouse?->name ?? '#'.$row->warehouse_id }}</td>
                        <td class="px-3 py-2">{{ $row->customer?->name ?? '#'.$row->customer_id }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $row->notes ?? '—' }}</td>
                        <td class="px-3 py-2 text-right">
                            <form method="POST" action="{{ route('reports.entities.fulfillment.destroy', $row) }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-600 hover:underline">Detach</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">No warehouse fulfillment mappings yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
