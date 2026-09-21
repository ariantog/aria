@php
    $multiSize = $multiSize ?? true;
    $curType  = $curType ?? null;
    $curJahit = $curJahit ?? null;
    $curWarna = $curWarna ?? null;
    $curSizes = $curSizes ?? [];
    $oldTags = old('tags', []);
    if (isset($oldTags['types'])) {
        $curType = is_array($oldTags['types']) ? ($oldTags['types'][0] ?? null) : $oldTags['types'];
    }
    if (isset($oldTags['jahit'])) {
        $curJahit = is_array($oldTags['jahit']) ? ($oldTags['jahit'][0] ?? null) : $oldTags['jahit'];
    }
    if (isset($oldTags['warna'])) {
        $curWarna = $oldTags['warna'];
    }
    if (isset($oldTags['sizes'])) {
        $curSizes = (array) $oldTags['sizes'];
    }
    $curWarnaPicker = ($isAsset && $multiSize)
        ? (array) (is_array($curWarna) ? $curWarna : array_filter([(string) ($curWarna ?? '')]))
        : (is_array($curWarna) ? ($curWarna[0] ?? null) : $curWarna);
    $curSizePicker = $multiSize
        ? (array) $curSizes
        : (is_array($curSizes) ? ($curSizes[0] ?? null) : $curSizes);
    $warnaError = $errors->has('tags.warna');
    $sizesError = $errors->has('tags.sizes');
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-purple-500/10 p-2">
            <svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Attributes</h3>
    </div>
    <div class="space-y-5 p-5">
        <fieldset class="space-y-4 rounded-lg border border-indigo-200 bg-indigo-50/40 p-4" data-testid="item-form-shared-tags">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-indigo-800">Shared across this colorway</legend>
            @include('items.partials.form-shared-banner', [
                'sharedTestId' => 'item-form-shared-tags-banner',
                'sharedHint' => 'Type, warna, and jahit apply to every size in this colorway.',
            ])
        {{-- Warna --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
                Warna (Color) <span class="text-red-500">*</span>
            </label>
            @include('items.partials.tag-filter-input', ['field' => 'warna', 'placeholder' => 'Filter warna…'])
            @include('items.partials.tag-picker-list', [
                'field' => 'warna',
                'tags' => $warnaTags,
                'inputName' => $isAsset && $multiSize ? 'tags[warna][]' : 'tags[warna]',
                'multiple' => $isAsset && $multiSize,
                'selected' => $curWarnaPicker,
                'onChange' => ($isAsset && $multiSize) ? 'onWarnaMulti' : 'onWarna',
                'errorBorder' => $warnaError,
            ])
            @error('tags.warna')<p class="mt-1 text-xs text-red-500">{{ is_array($message) ? implode(', ', $message) : $message }}</p>@enderror
            @unless($isAsset)
            <p class="mt-1 text-xs text-gray-500">One color per batch (for sales stats). Color number is also in the pcode suffix.</p>
            @endunless
        </div>

        {{-- Jahit (manufactured item only) --}}
        @unless($isAsset)
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Jahit <span class="text-red-500">*</span></label>
            @include('items.partials.tag-filter-input', ['field' => 'jahit', 'placeholder' => 'Filter jahit…'])
            @include('items.partials.tag-picker-list', [
                'field' => 'jahit',
                'tags' => $jahitTags,
                'inputName' => 'tags[jahit]',
                'multiple' => false,
                'selected' => $curJahit,
                'onChange' => null,
                'errorBorder' => $errors->has('tags.jahit'),
            ])
            @error('tags.jahit')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        @endunless

        {{-- Type tag (SKU category / restock TYPE tab) --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
                Type @if($isAsset)<span class="text-red-500">*</span>@endif
            </label>
            @include('items.partials.tag-filter-input', ['field' => 'type', 'placeholder' => 'Filter type…'])
            @include('items.partials.tag-picker-list', [
                'field' => 'type',
                'tags' => $typeTags,
                'inputName' => $isAsset ? 'tags[types][]' : 'tags[types]',
                'multiple' => false,
                'selected' => $curType,
                'onChange' => 'onTypeChange',
                'errorBorder' => $errors->has('tags.types'),
            ])
            @error('tags.types')<p class="mt-1 text-xs text-red-500">{{ is_array($message) ? implode(', ', $message) : $message }}</p>@enderror
            @if($isAsset)
            <p class="mt-1 text-xs text-gray-500">Used for restock TYPE tabs (e.g. ELBOW, BANDS).</p>
            @else
            <p class="mt-1 text-xs text-gray-500">Becomes the SKU prefix (e.g. AJD).</p>
            @endif
        </div>
        </fieldset>

        {{-- Size --}}
        <fieldset class="space-y-4 rounded-lg border border-gray-200 bg-gray-50/60 p-4" data-testid="item-form-sku-size">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-gray-700">This size only</legend>
            @include('items.partials.form-shared-banner', [
                'sharedTone' => 'sku',
                'sharedTitle' => 'This size only',
                'sharedHint' => $multiSize
                    ? 'Each selected size becomes its own SKU. Name is group name + color + size.'
                    : 'Size stays on this SKU. The display name is rebuilt as group name + color + size.',
                'sharedTestId' => 'item-form-sku-size-banner',
            ])
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Size <span class="text-red-500">*</span></label>
            @include('items.partials.tag-filter-input', ['field' => 'size', 'placeholder' => 'Filter size…'])
            @include('items.partials.tag-picker-list', [
                'field' => 'size',
                'tags' => $sizeTags,
                'inputName' => 'tags[sizes][]',
                'multiple' => $multiSize,
                'selected' => $curSizePicker,
                'onChange' => $multiSize ? 'onSizeMulti' : 'onSize',
                'errorBorder' => $sizesError,
            ])
            @error('tags.sizes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            @if($multiSize)
            <p class="mt-1 text-xs text-gray-500">Select all sizes to create in this batch. Use AS for all-size (no size suffix in SKU).</p>
            @endif
        </div>
        </fieldset>
    </div>
</div>
