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
    $showSkuDescriptions = ($isAsset ?? false) && $editingItem;
    $showAssetCatalogFields = ($isAsset ?? false);
    $parentProductName = $parentProductName ?? '';
    $parentGroupUrl = $parentGroupUrl ?? null;
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="item-catalog-panel" x-ref="catalogPanel">
    <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-yellow-500/10 p-2">
                <svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Catalog &amp; pricing</h3>
                <p class="text-xs text-gray-500">Three levels: this SKU → colorway → whole product group</p>
            </div>
        </div>
    </div>

    <div class="space-y-6 p-5">
        @include('items.partials.form-scope-toolbar')

        <input type="hidden" name="catalog_tab" :value="catalogTab">

        <div class="border-b border-gray-200" data-testid="item-catalog-tabs">
            <nav class="-mb-px flex flex-wrap gap-2" aria-label="Catalog level">
                <button type="button"
                        @click="catalogTab = 'size'"
                        data-testid="item-catalog-tab-size"
                        :class="catalogTab === 'size'
                            ? 'border-blue-600 text-blue-700 bg-blue-50/50'
                            : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900'"
                        class="rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition-colors">
                    This SKU
                </button>
                <button type="button"
                        @click="catalogTab = 'colorway'"
                        data-testid="item-catalog-tab-colorway"
                        :class="catalogTab === 'colorway'
                            ? 'border-indigo-600 text-indigo-800 bg-indigo-50/50'
                            : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900'"
                        class="rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition-colors">
                    Colorway
                </button>
                <button type="button"
                        @click="catalogTab = 'group'"
                        data-testid="item-catalog-tab-group"
                        :class="catalogTab === 'group'
                            ? 'border-amber-600 text-amber-900 bg-amber-50/50'
                            : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900'"
                        class="rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition-colors">
                    Whole group
                </button>
            </nav>
        </div>

        {{-- SKU level --}}
        <div x-show="catalogTab === 'size'" x-cloak class="space-y-4 rounded-lg border border-blue-100 bg-blue-50/30 p-4" data-testid="item-form-sku-details">
            @include('items.partials.form-shared-banner', [
                'sharedTone' => 'sku',
                'sharedTitle' => 'Stored on this SKU',
                'sharedHint' => $editingItem
                    ? ($showSkuDescriptions
                        ? 'Optional description overrides and restock threshold for this size only.'
                        : 'Restock urgency stays on this SKU; descriptions use the colorway tab.')
                    : 'Per-SKU overrides apply after create. Restock threshold seeds each new size in the batch.',
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
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-item-description2">Notes (NB) (this size)</label>
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
            <p class="text-xs text-gray-600">Amount scopes: use the pricing section below or the quick-scope buttons above.</p>
        </div>

        {{-- Colorway level --}}
        <div x-show="catalogTab === 'colorway'" x-cloak class="space-y-4 rounded-lg border border-indigo-100 bg-indigo-50/30 p-4" data-testid="item-form-shared-details">
            @include('items.partials.form-shared-banner', [
                'sharedTestId' => 'item-form-shared-details-banner',
                'sharedHint' => $editingItem
                    ? 'Saved on item_group for every size in this color. Saving clears stale per-SKU description copies unless you set a SKU override.'
                    : 'Applies to every size created in this batch.',
            ])
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-product-name">
                    Product name (colorway)
                    @if($isAsset ?? false)<span class="text-red-500">*</span>@endif
                </label>
                <input type="text" id="item-form-product-name" name="product_name" x-model="form.product_name"
                       value="{{ $fi['product_name'] }}"
                       data-testid="item-form-product-name"
                       @unless($isAsset ?? false) :placeholder="(form.pcode || '').toUpperCase() || 'CX90233-23'" @endunless
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('product_name') border-red-500 @enderror">
                @error('product_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-gray-500">Maps to <span class="font-mono">item_group.name</span>. Leave blank to keep pcode as the title.</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description">Description</label>
                <textarea id="item-form-description" name="description" rows="4"
                          data-testid="item-form-description"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">{{ $fi['description'] }}</textarea>
                @error('description')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-description2">Notes (NB)</label>
                <textarea id="item-form-description2" name="description2" rows="3"
                          data-testid="item-form-description2"
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
        </div>

        {{-- Parent group level --}}
        <div x-show="catalogTab === 'group'" x-cloak class="space-y-4 rounded-lg border border-amber-100 bg-amber-50/30 p-4" data-testid="item-form-group-details">
            @include('items.partials.form-shared-banner', [
                'sharedTone' => 'group',
                'sharedTitle' => 'Whole product group',
                'sharedHint' => 'Parent defaults apply when colorway and SKU values are empty (0 for amounts). Bulk parent edits clear lower overrides — use the group page for full control.',
                'sharedTestId' => 'item-form-group-details-banner',
            ])
            @if($parentGroupUrl)
            <div class="rounded-lg border border-amber-200 bg-white p-4 text-sm text-gray-800">
                <p class="font-medium text-gray-900">Parent product name</p>
                <p class="mt-1 font-mono text-gray-700">{{ $parentProductName !== '' ? $parentProductName : '— (inherits pcode / colorway titles)' }}</p>
                <a href="{{ $parentGroupUrl }}" class="mt-3 inline-flex text-sm font-medium text-amber-900 underline hover:text-amber-700">Edit parent group catalog →</a>
            </div>
            @else
            <p class="text-sm text-gray-700">Group-level defaults are available once this SKU belongs to a product group. On create, pick <strong>Whole group</strong> under pricing to seed parent amounts.</p>
            @endif
            <p class="text-xs text-gray-600">Parent-level description is not stored separately yet; group detail pages aggregate colorway text.</p>
        </div>

        @include('items.partials.form-pricing-scopes', [
            'pricingState' => $pricingState ?? [],
            'pricingPrefix' => 'pricing',
            'pricingIdPrefix' => 'item-form-pricing',
            'showEffective' => $editingItem,
            'pricingIntro' => 'Each amount has its own scope radio. Use the quick buttons above to align them all at once.',
            'embeddedInCatalog' => true,
        ])
    </div>
</div>
