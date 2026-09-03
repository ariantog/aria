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
    $editingItem = isset($item);
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-yellow-500/10 p-2">
            <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Details</h3>
    </div>
    <div class="space-y-6 p-5">
        <fieldset class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4" data-testid="item-form-shared-details">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-indigo-800">Shared across this colorway</legend>
            @include('items.partials.form-shared-banner', [
                'sharedTestId' => 'item-form-shared-details-banner',
                'sharedHint' => $editingItem
                    ? 'Description, notes, and URL are stored on the item group and copied to every size.'
                    : 'Description, notes, and URL apply to every size created in this batch.',
            ])
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description">Description</label>
                <textarea id="item-form-description" name="description" rows="4" placeholder="Colorway description..."
                          data-testid="item-form-description"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description'] }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Shared by every size in this colorway (stored on the item group).</p>
                @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description2">Notes (NB)</label>
                <textarea id="item-form-description2" name="description2" rows="3" placeholder="Additional notes..."
                          data-testid="item-form-description2"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description2'] }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Shared notes for the colorway (item group).</p>
                @error('description2')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-url">Product URL</label>
                <input type="url" id="item-form-url" name="url" value="{{ $fi['url'] }}" placeholder="https://..."
                       data-testid="item-form-url"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('url') border-red-500 @enderror">
                @error('url')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Optional link stored on the item group.</p>
            </div>
        </fieldset>

        <fieldset class="space-y-4 rounded-lg border border-gray-200 bg-gray-50/60 p-4" data-testid="item-form-sku-details">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-700">This size only</legend>
            @include('items.partials.form-shared-banner', [
                'sharedTone' => 'sku',
                'sharedTitle' => 'This size only',
                'sharedHint' => $editingItem
                    ? 'Restock urgency stays on this SKU. Other sizes are not changed.'
                    : 'Used as the starting threshold on each new SKU; later edits stay per size.',
                'sharedTestId' => 'item-form-sku-restock',
            ])
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-restock-threshold">Restock urgent threshold</label>
                <input type="number" id="item-form-restock-threshold" name="restock_urgent_threshold" min="1" step="1"
                       value="{{ $fi['restock_urgent_threshold'] }}" placeholder="e.g. 10"
                       data-testid="item-form-restock-threshold"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('restock_urgent_threshold') border-red-500 @enderror">
                @error('restock_urgent_threshold')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Optional. Used by restock sheets to flag low-stock urgency for this SKU.</p>
            </div>
        </fieldset>
    </div>
</div>
