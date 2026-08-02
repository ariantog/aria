@php
    $multiSize = $multiSize ?? true;
    $curType  = $curType ?? null;
    $curJahit = $curJahit ?? null;
    $curWarna = $curWarna ?? null;
    $curSizes = $curSizes ?? [];
    // Fallback to old() for repopulation on validation error
    $oldTags = old('tags', []);
    if (isset($oldTags['types'])) $curType = $oldTags['types'];
    if (isset($oldTags['jahit'])) $curJahit = $oldTags['jahit'];
    if (isset($oldTags['warna'])) $curWarna = $oldTags['warna'];
    if (isset($oldTags['sizes'])) $curSizes = (array) $oldTags['sizes'];
@endphp
<div class="rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
        <div class="rounded-lg bg-purple-500/10 p-2">
            <svg class="h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/></svg>
        </div>
        <h3 class="text-lg font-semibold text-gray-900">Attributes</h3>
    </div>
    <div class="space-y-5 p-5">
        {{-- Warna --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">
                Warna (Color) @if($isAsset)<span class="text-red-500">*</span>@endif
            </label>
            @if($isAsset)
                {{-- Multi (checkbox) for asset --}}
                <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-300 p-2">
                    @foreach($warnaTags as $t)
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50">
                        <input type="checkbox" name="tags[warna][]" value="{{ $t->id }}"
                               data-code="{{ $t->code }}"
                               @if(in_array((string)$t->id, array_map('strval',(array)$curWarna), true) || (string)$curWarna === (string)$t->id) checked @endif
                               @change="onWarnaMulti($event)" class="rounded border-gray-300">
                        {{ $t->name }} <span class="text-xs text-gray-400">({{ $t->code }})</span>
                    </label>
                    @endforeach
                </div>
            @else
                <select name="tags[warna]" @change="onWarna($event)"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">— Select Warna —</option>
                    @foreach($warnaTags as $t)
                    <option value="{{ $t->id }}" data-code="{{ $t->code }}" @selected((string)$curWarna === (string)$t->id)>{{ $t->name }} ({{ $t->code }})</option>
                    @endforeach
                </select>
            @endif
            @error('tags.warna')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        {{-- Jahit (item only) --}}
        @unless($isAsset)
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Jahit <span class="text-red-500">*</span></label>
            <select name="tags[jahit]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">— Select Jahit —</option>
                @foreach($jahitTags as $t)
                <option value="{{ $t->id }}" @selected((string)$curJahit === (string)$t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            @error('tags.jahit')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
        @endunless

        {{-- Type --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Type <span class="text-red-500">*</span></label>
            <select name="tags[types]" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="">— Select Type —</option>
                @foreach($typeTags as $t)
                <option value="{{ $t->id }}" @selected((string)$curType === (string)$t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
            @error('tags.types')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>

        {{-- Size --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-gray-700">Size <span class="text-red-500">*</span></label>
            @if($multiSize)
                <div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-300 p-2">
                    @foreach($sizeTags as $t)
                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50">
                        <input type="checkbox" name="tags[sizes][]" value="{{ $t->id }}"
                               data-code="{{ $t->code }}"
                               @if(in_array((string)$t->id, array_map('strval',(array)$curSizes), true)) checked @endif
                               @change="onSizeMulti($event)" class="rounded border-gray-300">
                        {{ $t->name }} <span class="text-xs text-gray-400">({{ $t->code }})</span>
                    </label>
                    @endforeach
                </div>
            @else
                <select name="tags[sizes][]" @change="onSize($event)"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    <option value="">— Select Size —</option>
                    @foreach($sizeTags as $t)
                    <option value="{{ $t->id }}" data-code="{{ $t->code }}" @selected(in_array((string)$t->id, array_map('strval',(array)$curSizes), true))>{{ $t->name }} ({{ $t->code }})</option>
                    @endforeach
                </select>
            @endif
            @error('tags.sizes')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
