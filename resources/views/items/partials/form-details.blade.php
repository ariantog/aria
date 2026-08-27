@php
    $fi = $formItem ?? [
        'description' => old('description'),
        'description2' => old('description2'),
        'url' => old('url'),
        'restock_urgent_threshold' => old('restock_urgent_threshold'),
    ];
    $fi['description'] = $fi['description'] ?? old('description');
    $fi['description2'] = $fi['description2'] ?? old('description2');
    $fi['url'] = $fi['url'] ?? old('url');
    $fi['restock_urgent_threshold'] = $fi['restock_urgent_threshold'] ?? old('restock_urgent_threshold');
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-yellow-500/10 p-2">
            <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Details</h3>
    </div>
    <div class="space-y-6 p-5">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" rows="4" placeholder="Item description..."
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description'] }}</textarea>
            @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Notes (NB)</label>
            <textarea name="description2" rows="3" placeholder="Additional notes..."
                      class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description2'] }}</textarea>
            @error('description2')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Product URL</label>
                <input type="url" name="url" value="{{ $fi['url'] }}" placeholder="https://..."
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('url') border-red-500 @enderror">
                @error('url')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Optional link to the product group page (shared by every SKU in this batch).</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Restock urgent threshold</label>
                <input type="number" name="restock_urgent_threshold" min="1" step="1"
                       value="{{ $fi['restock_urgent_threshold'] }}" placeholder="e.g. 10"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('restock_urgent_threshold') border-red-500 @enderror">
                @error('restock_urgent_threshold')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Optional. Used by restock sheets to flag low-stock urgency for this SKU.</p>
            </div>
        </div>
    </div>
</div>
