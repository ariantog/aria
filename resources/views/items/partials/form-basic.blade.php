@php
    $fi = $formItem ?? [
        'pcode' => old('pcode'),
    ];
    $pcodePlaceholder = $isAsset ? 'GLOVE-01' : 'CX90233-23';
    $editingItem = isset($item);
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-blue-500/10 p-2">
            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Identity</h3>
    </div>
    <div class="p-5">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700" for="item-form-pcode">Production Code (PCode) <span class="text-red-500">*</span></label>
            <input type="text" id="item-form-pcode" name="pcode" x-model="form.pcode" value="{{ $fi['pcode'] ?? old('pcode') }}" required
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
                Shared identifier for every size in the colorway.
                @if($isAsset)
                    Format: <span class="font-mono">TYPE-VARIANT</span> (e.g. GLOVE-01).
                @else
                    Format: <span class="font-mono">XX12345-23</span>.
                @endif
                Product name and catalog fields are under <span class="font-medium">Catalog &amp; pricing</span>.
            </p>
        </div>
    </div>
</div>
