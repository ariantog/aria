@php
    $fi = $formItem ?? [
        'pcode' => old('pcode'),
        'product_name' => old('product_name'),
        'price' => old('price'),
        'cost' => old('cost'),
    ];
    $pcodePlaceholder = $isAsset ? 'GLOVE-01' : 'CX90233-23';
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-blue-500/10 p-2">
            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Basic &amp; Financial Information</h3>
    </div>
    <div class="grid grid-cols-1 gap-6 p-5 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-pcode">Production Code (PCode) <span class="text-red-500">*</span></label>
            <input type="text" id="item-form-pcode" name="pcode" x-model="form.pcode" value="{{ $fi['pcode'] }}" required
                   data-testid="item-form-pcode"
                   placeholder="{{ $pcodePlaceholder }}" list="{{ $isAsset ? 'asset-pcode-suggestions' : '' }}"
                   @input="onPcodeInput()" @blur="onPcodeBlur()" @change="onPcodeBlur()"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('pcode') border-red-500 @enderror">
            @if($isAsset && !empty($assetPcodeSuggestions ?? []))
            <datalist id="asset-pcode-suggestions">
                @foreach($assetPcodeSuggestions as $suggestion)
                <option value="{{ $suggestion }}">
                @endforeach
            </datalist>
            @endif
            @error('pcode')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-gray-500">
                @if($isAsset)
                    Format: <span class="font-mono">TYPE-VARIANT</span> (e.g. GLOVE-01). Enter pcode first; the product name fills in when it already exists.
                @else
                    Format: <span class="font-mono">XX12345-23</span> — color number is in the pcode suffix. Existing pcodes load the product name.
                @endif
            </p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-product-name">
                Product Name
                @if($isAsset)<span class="text-red-500">*</span>@endif
            </label>
            <input type="text" id="item-form-product-name" name="product_name" x-model="form.product_name" value="{{ $fi['product_name'] }}"
                   data-testid="item-form-product-name"
                   @unless($isAsset) :placeholder="(form.pcode || '').toUpperCase() || 'CX90233-23'" @endunless
                   placeholder="{{ $isAsset ? 'e.g. Elbow Strap' : '' }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('product_name') border-red-500 @enderror">
            @error('product_name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-gray-500">
                SKU display name is <span class="font-mono">name - color - size</span>. All-size is <span class="font-mono">name - color</span>.
            </p>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Selling Price</label>
            <input type="number" step="any" name="price" value="{{ $fi['price'] }}" placeholder="0"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('price') border-red-500 @enderror">
            @error('price')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-gray-500">Applied to every SKU created in this batch (each row keeps its own price).</p>
        </div>
        @if($isAsset)
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Cost Price <span class="text-red-500">*</span></label>
            <input type="number" step="any" name="cost" value="{{ $fi['cost'] }}" required placeholder="0"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('cost') border-red-500 @enderror">
            @error('cost')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        @endif
    </div>
</div>
