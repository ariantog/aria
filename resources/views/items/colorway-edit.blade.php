@extends('layouts.app')

@section('title', 'Edit Colorway: ' . ($color['code'] ?? $group->name))

@section('content')
@php
$breadcrumbs = [
    ['title' => $isAsset ? 'Assets' : 'Items', 'href' => $isAsset ? route('assetlancar.index') : route('items.index')],
    ['title' => 'Groups', 'href' => route('items.group')],
    ['title' => $sample->pcode, 'href' => route('items.group-parent-detail', $parentGroupId)],
    ['title' => 'Edit colorway', 'href' => '#'],
];
@endphp

@php
    $colorwaySizeTagsJs = collect($sizeTags ?? [])->map(fn ($tag) => [
        'id' => $tag->id,
        'code' => $tag->code,
    ])->values()->all();
@endphp
<div class="p-4 sm:p-6" x-data="colorwayForm(@js([
    'product_name' => old('product_name', $productTitle),
    'pcode' => old('pcode', $sample->pcode),
    'isAsset' => $isAsset,
    'usesPlaceholder' => $usesPlaceholder,
    'parentTitle' => $titleForm['parent_title'] ?? '',
    'effectiveTitle' => $titleForm['effective_title'] ?? '',
    'rows' => $previewRows,
    'warnaCode' => $color['code'] ?? '',
    'warnaName' => $color['name'] ?? '',
    'sizeTags' => $colorwaySizeTagsJs,
]))" x-init="initColorwayTags()">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('items.group-parent-detail', $parentGroupId) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">
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

    <form id="colorway-save-form" method="POST" action="{{ route('items.colorway-update', $group) }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Identity --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm" data-testid="colorway-identity">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Identity</h3>
                        <p class="text-sm text-gray-500">Pcode drives master, variant, and SKU codes for every size here.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent product name</p>
                            <p class="text-sm font-medium text-gray-900" data-testid="colorway-parent-product-name">{{ $parentProductName !== '' ? $parentProductName : '— (not set on parent group)' }}</p>
                            <p class="mt-0.5 text-xs text-gray-500">Edit on the <a href="{{ route('items.group-parent-detail', $parentGroupId) }}" class="font-medium text-blue-600 underline hover:text-blue-800">parent group</a> page.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Parent group description</p>
                            <p class="text-sm text-gray-800 whitespace-pre-wrap" data-testid="colorway-parent-description">{{ ($parentDescription ?? '') !== '' ? $parentDescription : '— (not set on parent group)' }}</p>
                            @if(($parentDescription2 ?? '') !== '')
                            <p class="mt-1 text-sm text-gray-600 whitespace-pre-wrap"><span class="font-medium text-gray-500">NB:</span> {{ $parentDescription2 }}</p>
                            @endif
                            <p class="mt-0.5 text-xs text-gray-500">Shared default for all colorways — edit on the parent group page.</p>
                        </div>
                        <div>
                            <label for="colorway-pcode" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">PCode</label>
                            <input type="text" id="colorway-pcode" name="pcode" x-model="form.pcode"
                                   value="{{ old('pcode', $sample->pcode) }}"
                                   data-testid="colorway-pcode"
                                   required
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Use hyphens (e.g. CI00122-08). Saving rebuilds SKU codes for all sizes.</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Attributes</p>
                            <p class="text-sm text-gray-600">Type, warna, and jahit are in the column on the right. Size, pricing, description, and ongkir are edited in the SKU matrix below.</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Master (after save)</p>
                            <p class="font-mono text-sm text-gray-700">{{ $group->master ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variant (after save)</p>
                            <p class="font-mono text-sm text-gray-700">{{ $group->variant ?: '—' }}</p>
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
                        @include('items.partials.form-colorway-title-hints', ['titleForm' => $titleForm ?? []])
                        <div>
                            <label for="colorway-product-name" class="mb-1 block text-sm font-medium text-gray-700">
                                Stored product name (this colorway)
                            </label>
                            <input type="text" id="colorway-product-name" name="product_name" x-model="form.product_name"
                                   value="{{ old('product_name', $productTitle) }}"
                                   data-testid="colorway-product-name"
                                   @unless($isAsset) placeholder="Leave blank to inherit parent group name" @endunless
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <p class="mt-1 text-xs text-gray-500">Maps to <span class="font-mono">item_group.name</span> for this color only. Empty field = inherit parent; the SKU name preview uses the effective title.</p>
                        </div>
                        <div>
                            <label for="colorway-url" class="mb-1 block text-sm font-medium text-gray-700">URL</label>
                            <input type="text" id="colorway-url" name="url" inputmode="url"
                                   value="{{ old('url', $group->url) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @include('items.partials.form-attributes', [
                    'attributesScope' => 'colorway-shared',
                    'multiSize' => false,
                    'curType' => $curType,
                    'curJahit' => $curJahit,
                    'curWarna' => $curWarna,
                ])
            </div>
        </div>

        @include('items.partials.colorway-sku-matrix', [
            'sizeRows' => $sizeRows,
            'sizeTags' => $sizeTags,
            'isAsset' => $isAsset,
            'parentDescription' => $parentDescription ?? '',
            'parentDescription2' => $parentDescription2 ?? '',
        ])

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @include('items.partials.form-image', ['imageUrl' => $group->image_url])

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

        <div class="sticky bottom-0 z-10 -mx-4 mt-8 flex justify-end gap-4 border-t border-gray-200 bg-white/95 px-4 py-4 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] backdrop-blur-sm sm:-mx-6 sm:px-6">
            <a href="{{ route('items.group-parent-detail', $parentGroupId) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-100">Cancel</a>
            <button type="submit" form="colorway-save-form" data-testid="colorway-save" class="min-w-[150px] rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save colorway</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function colorwayForm(config) {
    return {
        form: {
            product_name: config.product_name || '',
            pcode: config.pcode || '',
        },
        rows: config.rows || [],
        pcode: config.pcode || '',
        isAsset: config.isAsset,
        usesPlaceholder: config.usesPlaceholder,
        parentTitle: (config.parentTitle || '').toUpperCase().trim(),
        effectiveTitle: (config.effectiveTitle || '').toUpperCase().trim(),
        warnaCode: (config.warnaCode || '').toUpperCase(),
        warnaName: (config.warnaName || config.warnaCode || '').toUpperCase(),
        allSizeCode: 'AS',
        sizeTags: config.sizeTags || [],
        draftRows: [],
        draftSeq: 0,
        mass: {
            price: '',
            reseller_price: '',
            cost: '',
            cost_cnh: '',
            ongkir: '',
            description: '',
            description2: '',
        },
        tagFilters: {
            warna: '',
            type: '',
            size: '',
            jahit: '',
        },

        tagOptionMatches(name, code, query) {
            const q = String(query || '').trim().toLowerCase();
            if (!q) {
                return true;
            }
            const label = String(name || '').toLowerCase();
            const tagCode = String(code || '').toLowerCase();

            return label.includes(q) || tagCode.includes(q);
        },

        tagOptionVisible(field, name, code) {
            return this.tagOptionMatches(name, code, this.tagFilters[field]);
        },

        tagHasVisibleOptions(field) {
            const list = this.$root.querySelector(`[data-testid="tag-picker-${field}"]`);
            if (!list) {
                return true;
            }

            return [...list.querySelectorAll('label')].some((el) => el.offsetParent !== null);
        },

        syncWarnaFromDom() {
            const warnaInput = this.$root.querySelector('input[name="tags[warna]"]:checked');
            if (warnaInput) {
                this.warnaCode = warnaInput.dataset.code || this.warnaCode;
                this.warnaName = warnaInput.dataset.name || this.warnaCode;
            }
        },

        onWarna() {
            this.syncWarnaFromDom();
        },

        onTypeChange() {
        },

        initColorwayTags() {
            this.$nextTick(() => {
                this.syncWarnaFromDom();
            });
        },

        applyMassPricing(field) {
            const raw = this.mass[field];
            if (raw === '' || raw === null || raw === undefined) {
                return;
            }

            this.$root.querySelectorAll('[data-mass-pricing="' + field + '"]').forEach((el) => {
                el.value = raw;
                el.dispatchEvent(new Event('input', { bubbles: true }));
            });
        },

        applyMassFillBlank(field) {
            const raw = String(this.mass[field] ?? '').trim();
            if (raw === '') {
                return;
            }

            this.$root.querySelectorAll('[data-mass-text="' + field + '"]').forEach((el) => {
                if (String(el.value ?? '').trim() === '') {
                    el.value = raw;
                }
            });
        },

        addDraftRow() {
            this.draftSeq += 1;
            const key = 'd' + this.draftSeq;
            const pricing = { price: '', reseller_price: '' };
            if (this.isAsset) {
                pricing.cost = '';
                pricing.cost_cnh = '';
            }
            this.draftRows.push({
                key,
                size_tag_id: '',
                pricing,
                ongkir: '',
                restock: '',
                description: '',
                description2: '',
            });
        },

        removeDraftRow(index) {
            this.draftRows.splice(index, 1);
        },

        get displayTitle() {
            const name = (this.form.product_name || '').toUpperCase().trim();
            if (name) {
                return name;
            }

            if (this.parentTitle) {
                return this.parentTitle;
            }

            return (this.form.pcode || this.pcode || '').toUpperCase().trim();
        },

        buildName(title, sizeCode) {
            const sc = (sizeCode || '').toUpperCase();
            const parts = [title];
            const wn = (this.warnaName || this.warnaCode || '').toUpperCase().trim();
            if (wn && wn !== '???') {
                parts.push(wn);
            }
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
