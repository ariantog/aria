@php
    $fi = $formItem ?? [
        'description' => old('description'),
        'description2' => old('description2'),
        'item_description' => old('item_description'),
        'item_description2' => old('item_description2'),
        'url' => old('url'),
        'restock_urgent_threshold' => old('restock_urgent_threshold'),
        'product_name' => old('product_name'),
    ];
    $fi['description'] = $fi['description'] ?? old('description');
    $fi['description2'] = $fi['description2'] ?? old('description2');
    $fi['item_description'] = $fi['item_description'] ?? old('item_description');
    $fi['item_description2'] = $fi['item_description2'] ?? old('item_description2');
    $fi['url'] = $fi['url'] ?? old('url');
    $fi['restock_urgent_threshold'] = $fi['restock_urgent_threshold'] ?? old('restock_urgent_threshold');
    $fi['product_name'] = $fi['product_name'] ?? old('product_name');
    $editingItem = isset($item);
    $catalogMode = $catalogMode ?? 'full';
    $skuOnlyCatalog = $editingItem && $catalogMode === 'sku-only';
    $showSkuDescriptions = ($isAsset ?? false) && $editingItem;
    $showAssetCatalogFields = ($isAsset ?? false);
    $parentProductName = $parentProductName ?? '';
    $parentGroupUrl = $parentGroupUrl ?? null;
    $titleForm = $titleForm ?? [];
    $colorwayEditUrl = $colorwayEditUrl ?? null;
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="item-catalog-panel" x-ref="catalogPanel">
    <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-yellow-500/10 p-2">
                <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Catalog &amp; pricing</h3>
                <p class="text-xs text-gray-500">
                    @if($skuOnlyCatalog)
                        SKU-level amounts and restock only — shared catalog is edited on the group / colorway pages.
                    @else
                        Colorway catalog for every size in this batch; pricing saves on the parent group or each new SKU automatically.
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="space-y-6 p-5">
        <input type="hidden" name="catalog_tab" value="{{ $skuOnlyCatalog ? 'size' : 'colorway' }}">

        @if($skuOnlyCatalog)
        <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 p-4 text-sm text-indigo-950" data-testid="item-form-catalog-links">
            <p class="font-medium">Shared catalog lives on group pages</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-indigo-900">
                @if($parentGroupUrl)
                <li>
                    <a href="{{ $parentGroupUrl }}" class="font-medium underline hover:text-indigo-700">Parent group</a>
                    — product name, descriptions @if(!($isAsset ?? false))and brand/type @endif defaults
                    @if($parentProductName !== '')
                        (current parent title: <span class="font-semibold">{{ $parentProductName }}</span>)
                    @endif
                </li>
                @endif
                @if($colorwayEditUrl)
                <li>
                    <a href="{{ $colorwayEditUrl }}" class="font-medium underline hover:text-indigo-700">Colorway editor</a>
                    — pcode, colorway product name, descriptions, image, colorway pricing matrix
                </li>
                @endif
            </ul>
        </div>

        <div class="space-y-4 rounded-lg border border-blue-100 bg-blue-50/30 p-4" data-testid="item-form-sku-details">
            @include('items.partials.form-shared-banner', [
                'sharedTone' => 'sku',
                'sharedTitle' => 'Stored on this SKU',
                'sharedHint' => $showSkuDescriptions
                    ? 'Optional description overrides and restock threshold for this size only.'
                    : 'Restock urgency for this size only.',
                'sharedTestId' => 'item-form-sku-details-banner',
            ])
            @if($showSkuDescriptions)
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-item-description">Description (this size)</label>
                <textarea id="item-form-item-description" name="item_description" rows="4" placeholder="Override description for this SKU..."
                          data-testid="item-form-item-description"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['item_description'] }}</textarea>
                @error('item_description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-item-description2">Notes (NB) (this size) <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea id="item-form-item-description2" name="item_description2" rows="3"
                          data-testid="item-form-item-description2"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['item_description2'] }}</textarea>
                @error('item_description2')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            @endif
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-restock-threshold">Restock urgent threshold</label>
                <input type="number" id="item-form-restock-threshold" name="restock_urgent_threshold" min="1" step="1"
                       value="{{ $fi['restock_urgent_threshold'] }}" placeholder="e.g. 10"
                       data-testid="item-form-restock-threshold"
                       class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('restock_urgent_threshold') border-red-500 @enderror">
                @error('restock_urgent_threshold')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
        </div>
        @else
        <div class="space-y-4 rounded-lg border border-indigo-100 bg-indigo-50/30 p-4" data-testid="item-form-shared-details">
            @include('items.partials.form-shared-banner', [
                'sharedTestId' => 'item-form-shared-details-banner',
                'sharedHint' => 'Applies to every size created in this batch (stored on item_group). Per-SKU overrides are available after create.',
            ])
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-product-name">
                    Stored product name (this colorway)
                </label>
                <input type="text" id="item-form-product-name" name="product_name" x-model="form.product_name"
                       value="{{ $fi['product_name'] }}"
                       data-testid="item-form-product-name"
                       @unless($isAsset ?? false) placeholder="Leave blank to inherit parent group name" @endunless
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('product_name') border-red-500 @enderror">
                @error('product_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Stored on <span class="font-mono">item_group.name</span>. Blank = inherit parent product name when the parent row exists.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description">Description (this colorway)</label>
                <textarea id="item-form-description" name="description" rows="4"
                          data-testid="item-form-description"
                          placeholder="Leave blank to inherit parent group description"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description'] }}</textarea>
                @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Blank inherits the parent group default when set.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description2">Notes (NB) (this colorway) <span class="font-normal text-gray-400">(optional)</span></label>
                <textarea id="item-form-description2" name="description2" rows="3"
                          data-testid="item-form-description2"
                          placeholder="Leave blank to inherit parent group notes"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description2'] }}</textarea>
                @error('description2')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-url">Product URL</label>
                <input type="url" id="item-form-url" name="url" value="{{ $fi['url'] }}"
                       data-testid="item-form-url"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('url') border-red-500 @enderror">
                @error('url')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-restock-threshold">Restock urgent threshold</label>
                <input type="number" id="item-form-restock-threshold" name="restock_urgent_threshold" min="1" step="1"
                       value="{{ $fi['restock_urgent_threshold'] }}" placeholder="e.g. 10"
                       data-testid="item-form-restock-threshold"
                       class="w-full max-w-xs rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('restock_urgent_threshold') border-red-500 @enderror">
                @error('restock_urgent_threshold')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Default for each SKU created in this batch.</p>
            </div>
        </div>

        @include('items.partials.form-pricing-create', ['formItem' => $fi])
        @endif

        @if($skuOnlyCatalog)
        @include('items.partials.form-pricing-scopes', [
            'pricingState' => $pricingState ?? [],
            'pricingPrefix' => 'pricing',
            'pricingIdPrefix' => 'item-form-pricing',
            'showEffective' => false,
            'pricingFixedScope' => \App\Support\ItemPricing::SCOPE_SIZE,
            'pricingFieldKeys' => ! ($isAsset ?? false)
                ? ['price', 'reseller_price']
                : null,
            'pricingIntro' => 'Amounts are stored on this SKU only. Fields show the effective value (parent default when this SKU has no override).',
            'embeddedInCatalog' => true,
        ])
        @endif
    </div>
</div>
