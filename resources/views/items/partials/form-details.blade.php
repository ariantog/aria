@php $fi = $formItem ?? ['description'=>old('description'),'description2'=>old('description2')]; @endphp
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
    </div>
</div>
