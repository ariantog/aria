@php $fi = $formItem ?? ['pcode'=>old('pcode'),'name'=>old('name'),'alias'=>old('alias'),'price'=>old('price'),'cost'=>old('cost')]; @endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-blue-500/10 p-2">
            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Basic &amp; Financial Information</h3>
    </div>
    <div class="grid grid-cols-1 gap-6 p-5 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Code (PCode) <span class="text-red-500">*</span></label>
            <input type="text" name="pcode" x-model="form.pcode" value="{{ $fi['pcode'] }}" required placeholder="e.g. BOXING-01"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('pcode') border-red-500 @enderror">
            @error('pcode')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Alias</label>
            <input type="text" name="alias" x-model="form.alias" value="{{ $fi['alias'] }}" placeholder="Alternative Name"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        @if($isAsset)
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ $fi['name'] }}" required placeholder="Asset Name"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror">
            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        @endif
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Selling Price</label>
            <input type="number" step="any" name="price" value="{{ $fi['price'] }}" placeholder="0"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 @error('price') border-red-500 @enderror">
            @error('price')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
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
