@extends('layouts.app')

@section('title', 'Edit Colorway: ' . ($color['code'] ?? $group->name))

@section('content')
@php
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $sample->pcode, 'href' => route('items.group-parent-detail', $parentSlug)],
    ['title' => 'Edit colorway', 'href' => '#'],
];
$previewRows = collect($sizeRows)->map(fn ($row) => [
    'code' => $row['code'],
    'size_code' => $row['size_code'],
    'warna_code' => $row['warna_code'],
])->values()->all();
@endphp

<div class="p-4 sm:p-6" x-data="colorwayForm(@js([
    'product_name' => old('product_name', $productTitle),
    'pcode' => $sample->pcode,
    'isAsset' => $isAsset,
    'usesPlaceholder' => $usesPlaceholder,
    'rows' => $previewRows,
    'warnaCode' => $color['code'] ?? '',
    'warnaName' => $color['name'] ?? '',
]))">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('items.group-parent-detail', $parentSlug) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to group
        </a>
        <p class="text-sm text-gray-500">Colorway editor — catalog &amp; price matrix for one color</p>
    </div>

    @if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('items.colorway-update', $group) }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Read-only identity --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-identity-readonly">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Identity (read-only)</h3>
                        <p class="text-sm text-gray-500">Master, variant, pcode, and SKU codes are generated from tags and pcode.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Master</p>
                            <p class="font-mono text-sm text-gray-900">{{ $group->master ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variant</p>
                            <p class="font-mono text-sm text-gray-900">{{ $group->variant ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">PCode</p>
                            <p class="font-mono text-sm text-gray-900">{{ $sample->pcode }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Color</p>
                            <p class="text-sm text-gray-900">{{ $color['code'] }} — {{ $color['name'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- Editable catalog --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-catalog">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Catalog (this colorway)</h3>
                        <p class="text-sm text-gray-500">Shared by every size in this colorway (stored on item_group).</p>
                    </div>
                    <div class="space-y-4 p-5">
                        <div>
                            <label for="colorway-product-name" class="mb-1 block text-sm font-medium text-gray-700">
                                Product name
                                @if($isAsset)<span class="text-red-500">*</span>@endif
                            </label>
                            <input type="text" id="colorway-product-name" name="product_name" x-model="form.product_name"
                                   data-testid="colorway-product-name"
                                   @unless($isAsset) placeholder="{{ $sample->pcode }}" @endunless
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Maps to <span class="font-mono">item_group.name</span>. SKU display names update on save.</p>
                        </div>
                        <div>
                            <label for="colorway-description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" id="colorway-description" name="description"
                                   value="{{ old('description', $group->description) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="colorway-description2" class="mb-1 block text-sm font-medium text-gray-700">Description 2</label>
                            <input type="text" id="colorway-description2" name="description2"
                                   value="{{ old('description2', $group->description2) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="colorway-url" class="mb-1 block text-sm font-medium text-gray-700">URL</label>
                            <input type="url" id="colorway-url" name="url"
                                   value="{{ old('url', $group->url) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="colorway-brand" class="mb-1 block text-sm font-medium text-gray-700">Brand</label>
                                <select id="colorway-brand" name="brand" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand['value'] }}" @selected((int) old('brand', $group->brand?->value ?? 0) === (int) $brand['value'])>{{ $brand['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="colorway-genre" class="mb-1 block text-sm font-medium text-gray-700">Genre (type tag)</label>
                                <select id="colorway-genre" name="genre" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    <option value="0">—</option>
                                    @foreach($typeTags as $tag)
                                    <option value="{{ $tag->id }}" @selected((int) old('genre', $group->genre ?? 0) === (int) $tag->id)>{{ $tag->code }} — {{ $tag->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Per-size matrix --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-size-matrix">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Sizes &amp; pricing</h3>
                        <p class="text-sm text-gray-500">Each row is one SKU. Price, cost, and restock threshold are per size.</p>
                    </div>
                    <div class="overflow-x-auto p-4">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Size</th>
                                    <th class="px-3 py-2 text-left font-semibold">SKU</th>
                                    <th class="px-3 py-2 text-right font-semibold">Price</th>
                                    @if($isAsset)
                                    <th class="px-3 py-2 text-right font-semibold">Cost</th>
                                    @endif
                                    <th class="px-3 py-2 text-right font-semibold">Restock urgent</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($sizeRows as $row)
                                <tr>
                                    <td class="px-3 py-3 font-semibold text-gray-900">{{ $row['size_code'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="font-mono text-blue-600">{{ $row['code'] }}</span>
                                        <input type="hidden" name="items[{{ $row['id'] }}][_key]" value="1">
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <input type="number" step="any" name="items[{{ $row['id'] }}][price]"
                                               value="{{ $row['price'] }}"
                                               data-testid="colorway-price-{{ $row['id'] }}"
                                               class="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    @if($isAsset)
                                    <td class="px-3 py-3 text-right">
                                        <input type="number" step="any" name="items[{{ $row['id'] }}][cost]"
                                               value="{{ $row['cost'] }}"
                                               class="w-28 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    @endif
                                    <td class="px-3 py-3 text-right">
                                        <input type="number" min="1" step="1" name="items[{{ $row['id'] }}][restock_urgent_threshold]"
                                               value="{{ $row['restock_urgent_threshold'] }}"
                                               placeholder="—"
                                               class="w-24 rounded-lg border border-gray-300 px-2 py-1.5 text-right text-sm font-mono focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @include('items.partials.form-image', ['imageUrl' => $group->image_url])

                {{-- Live name preview --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-name-preview">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">SKU names (preview)</h3>
                        <p class="text-sm text-gray-500">Updates as you type the product name.</p>
                    </div>
                    <div class="max-h-[420px] space-y-2 overflow-y-auto p-5">
                        <template x-for="(row, idx) in previewNames" :key="idx">
                            <div class="rounded-lg border border-gray-100 bg-gray-50/80 p-3">
                                <p class="font-mono text-xs text-blue-600" x-text="row.code"></p>
                                <p class="mt-1 text-sm font-medium text-gray-900" x-text="row.name"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-4 border-t border-gray-200 pt-8">
            <a href="{{ route('items.group-parent-detail', $parentSlug) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100">Cancel</a>
            <button type="submit" data-testid="colorway-save" class="min-w-[150px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save colorway</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function colorwayForm(config) {
    return {
        form: {
            product_name: config.product_name || '',
        },
        rows: config.rows || [],
        pcode: config.pcode || '',
        isAsset: config.isAsset,
        usesPlaceholder: config.usesPlaceholder,
        warnaCode: (config.warnaCode || '').toUpperCase(),
        warnaName: (config.warnaName || config.warnaCode || '').toUpperCase(),
        allSizeCode: 'AS',

        get displayTitle() {
            const name = (this.form.product_name || '').toUpperCase().trim();
            if (name) {
                return name;
            }

            return (this.pcode || '').toUpperCase().trim();
        },

        buildName(title, sizeCode) {
            const wn = this.warnaName || this.warnaCode || '???';
            const sc = (sizeCode || '').toUpperCase();
            const parts = [title, wn];
            if (sc && sc !== this.allSizeCode && sc !== '—') {
                parts.push(sc);
            }

            return parts.join(' - ');
        },

        get previewNames() {
            return this.rows.map((row) => ({
                code: row.code,
                name: this.buildName(this.displayTitle, row.size_code),
            }));
        },
    };
}
</script>
@endpush
@endsection
