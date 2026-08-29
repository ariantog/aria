@php
$life = old('useful_life_months', $register->useful_life_months ?? 48);
$residual = old('residual_value', $register->residual_value ?? 0);
$warehouseId = old('warehouse_id', $register->warehouse_id ?? '');
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div class="space-y-1.5 md:col-span-2">
            <label for="name" class="text-sm font-medium text-gray-700">Nama</label>
            <input type="text" id="name" name="name" value="{{ old('name', $item?->name ?? '') }}" required
                   data-testid="assettetap-name"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="space-y-1.5">
            <label for="code" class="text-sm font-medium text-gray-700">Kode <span class="font-normal text-gray-400">(opsional)</span></label>
            <input type="text" id="code" name="code" value="{{ old('code', $item?->code ?? '') }}"
                   data-testid="assettetap-code"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                   placeholder="Otomatis AT-{id}">
        </div>
        <div class="space-y-1.5">
            <label for="useful_life_months" class="text-sm font-medium text-gray-700">Umur manfaat (bulan)</label>
            <input type="number" id="useful_life_months" name="useful_life_months" min="1" max="1200" required
                   value="{{ $life }}" data-testid="assettetap-life"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="space-y-1.5">
            <label for="residual_value" class="text-sm font-medium text-gray-700">Nilai residu</label>
            <input type="number" id="residual_value" name="residual_value" min="0" step="0.01"
                   value="{{ $residual }}" data-testid="assettetap-residual"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="space-y-1.5">
            <label for="warehouse_id" class="text-sm font-medium text-gray-700">Gudang / lokasi</label>
            <select id="warehouse_id" name="warehouse_id" data-testid="assettetap-warehouse"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                <option value="">—</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-1.5 md:col-span-2">
            <label for="description" class="text-sm font-medium text-gray-700">Deskripsi</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">{{ old('description', $item?->description ?? '') }}</textarea>
        </div>
        <div class="space-y-1.5 md:col-span-2">
            <label for="notes" class="text-sm font-medium text-gray-700">Catatan register</label>
            <input type="text" id="notes" name="notes" value="{{ old('notes', $register->notes ?? '') }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
        </div>
    </div>
</div>
