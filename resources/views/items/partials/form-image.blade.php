@php $imageUrl = $imageUrl ?? null; @endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm" x-data="{ preview: @js($imageUrl) }">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-pink-500/10 p-2">
            <svg class="h-5 w-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Image</h3>
    </div>
    <div class="p-5">
        <label class="flex aspect-[4/3] w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 hover:border-blue-400">
            <template x-if="preview">
                <img :src="preview" class="h-full w-full object-cover">
            </template>
            <template x-if="!preview">
                <div class="flex flex-col items-center gap-2 text-gray-400">
                    <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                    <span class="text-sm">Click to upload image</span>
                </div>
            </template>
            <input type="file" name="image" accept="image/*" class="hidden"
                   @change="const f=$event.target.files[0]; if(f){ preview = URL.createObjectURL(f); }">
        </label>
        @error('image')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
    </div>
</div>
