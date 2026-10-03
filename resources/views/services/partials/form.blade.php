@php
    $item = $item ?? null;
    $defaults = $defaults ?? ['tanpa_stok' => true, 'allow_decimal_quantity' => false];
    $tanpaStok = old('tanpa_stok', $item ? ! $item->tracksInventory() : ($defaults['tanpa_stok'] ?? true));
    $allowDecimal = old('allow_decimal_quantity', $item ? $item->allowsDecimalQuantity() : ($defaults['allow_decimal_quantity'] ?? false));
@endphp

<div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="service-code">SKU / Kode</label>
            <input id="service-code" name="code" required maxlength="50"
                   value="{{ old('code', $item?->code) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm @error('code') border-red-500 @enderror">
            @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="service-name">Nama</label>
            <input id="service-name" name="name" required maxlength="255"
                   value="{{ old('name', $item?->name) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm @error('name') border-red-500 @enderror">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="service-price">Harga jual</label>
            <input id="service-price" name="price" type="number" min="0" step="0.01" required
                   value="{{ old('price', $item?->price) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm @error('price') border-red-500 @enderror">
            @error('price')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="service-cost">Cost (opsional)</label>
            <input id="service-cost" name="cost" type="number" min="0" step="0.01"
                   value="{{ old('cost', $item?->cost) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>
    </div>
    <div>
        <label class="mb-1 block text-sm font-medium text-gray-700" for="service-description">Deskripsi</label>
        <textarea id="service-description" name="description" rows="2"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('description', $item?->catalogDescription()) }}</textarea>
    </div>

    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3" data-testid="service-inventory-options">
        <h3 class="text-sm font-semibold text-gray-900">Inventory &amp; quantity</h3>
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="tanpa_stok" value="1" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                   @checked($tanpaStok) data-testid="service-tanpa-stok">
            <span>
                <span class="font-medium">Tanpa stok</span>
                <span class="block text-xs text-gray-500">Default untuk service — tidak memotong stok gudang.</span>
            </span>
        </label>
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox" name="allow_decimal_quantity" value="1" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                   @checked($allowDecimal) data-testid="service-qty-desimal">
            <span>
                <span class="font-medium">Qty desimal</span>
                <span class="block text-xs text-gray-500">Contoh 25.9 untuk luas m²; nonaktif = qty bulat (sesi training).</span>
            </span>
        </label>
    </div>
</div>
