@php
    $multiSize = $multiSize ?? true;
    $isAsset = $isAsset ?? false;
    $formItem = $formItem ?? [
        'pcode' => old('pcode', ''),
        'product_name' => old('product_name', ''),
    ];
@endphp
@push('scripts')
<script>
function itemForm() {
    return {
        isAsset: @json($isAsset),
        multiSize: @json($multiSize),
        multiWarna: @json($isAsset && $multiSize),
        allSizeCode: 'AS',
        itemType: @js((int) ($itemType ?? ($isAsset ? 2 : 1))),
        pcodeNameUrl: @js(route('items.pcode-name')),
        autoFilledName: '',
        pcodeLookupTimer: null,
        form: {
            pcode: @js($formItem['pcode'] ?? ''),
            product_name: @js($formItem['product_name'] ?? ''),
        },
        typeCode: '???',
        warnaCode: '???',
        sizeCode: '???',
        warnaCodes: [],
        sizeCodes: [],

        init() {
            this.autoFilledName = (this.form.product_name || '').toUpperCase().trim();
            this.$nextTick(() => this.syncFromDom());
        },

        syncFromDom() {
            const root = this.$root;

            const typeSel = root.querySelector('select[name="tags[types]"]');
            if (typeSel?.selectedOptions[0]?.value) {
                this.typeCode = typeSel.selectedOptions[0].dataset.code || '???';
            }

            const warnaSel = root.querySelector('select[name="tags[warna]"]');
            if (warnaSel?.selectedOptions[0]?.value) {
                this.warnaCode = warnaSel.selectedOptions[0].dataset.code || '???';
            }

            const sizeSel = root.querySelector('select[name="tags[sizes][]"]');
            if (sizeSel?.selectedOptions[0]?.value) {
                this.sizeCode = sizeSel.selectedOptions[0].dataset.code || '???';
            }

            const warnaChecks = root.querySelectorAll('input[name="tags[warna][]"]:checked');
            if (warnaChecks.length) {
                this.warnaCodes = [...warnaChecks].map(i => ({ code: i.dataset.code || '???' }));
            }

            const sizeChecks = root.querySelectorAll('input[name="tags[sizes][]"]:checked');
            if (sizeChecks.length) {
                this.sizeCodes = [...sizeChecks].map(i => ({ code: i.dataset.code || '???' }));
            }
        },

        get previewItems() {
            const pcode = (this.form.pcode || '').toUpperCase().trim();
            const productName = (this.form.product_name || '').toUpperCase().trim()
                || (this.isAsset ? '???' : pcode || '???');
            if (!pcode) {
                return [];
            }

            const items = [];
            const appendRow = (sku, name) => items.push({ sku, name });

            if (this.isAsset && this.multiWarna) {
                const sizes = this.sizeCodes;
                const warnas = this.warnaCodes;
                if (!sizes.length || !warnas.length) {
                    return [];
                }
                sizes.forEach(s => {
                    warnas.forEach(w => {
                        const sc = (s.code || '???').toUpperCase();
                        const wc = (w.code || '???').toUpperCase();
                        const sku = this.appendSizeSegment(`${pcode}-${wc}`, sc);
                        appendRow(sku, this.buildDisplayName(productName, wc, sc));
                    });
                });

                return items;
            }

            if (this.multiSize) {
                const sizes = this.sizeCodes;
                const wc = (this.warnaCode || '???').toUpperCase();
                const tc = (this.typeCode || '???').toUpperCase();
                if (!sizes.length || (!this.isAsset && tc === '???')) {
                    return [];
                }
                sizes.forEach(s => {
                    const sc = (s.code || '???').toUpperCase();
                    const sku = this.isAsset
                        ? this.appendSizeSegment(`${pcode}-${wc}`, sc)
                        : this.appendSizeSegment(`${tc}-${pcode}`, sc);
                    const nameSuffix = sc === this.allSizeCode ? '' : ` - ${sc}`;
                    appendRow(sku, this.buildDisplayName(productName, wc, sc));
                });

                return items;
            }

            const wc = (this.warnaCode || '???').toUpperCase();
            const sc = (this.sizeCode || '???').toUpperCase();
            const tc = (this.typeCode || '???').toUpperCase();
            const sku = this.isAsset
                ? this.appendSizeSegment(`${pcode}-${wc}`, sc)
                : this.appendSizeSegment(`${tc}-${pcode}`, sc);
            appendRow(sku, this.buildDisplayName(productName, wc, sc));

            return items;
        },

        buildDisplayName(productName, warnaCode, sizeCode) {
            let title = (productName || '').toUpperCase().trim();
            const warna = (warnaCode || '').toUpperCase().trim();
            if (warna && title.endsWith(' - ' + warna)) {
                title = title.slice(0, -(warna.length + 3)).trim();
            }
            if (title.includes(' - ')) {
                title = title.split(' - ')[0].trim();
            }

            const parts = [title];
            if (warna && warna !== '???') {
                parts.push(warna);
            }
            if (sizeCode && sizeCode !== '???' && sizeCode !== this.allSizeCode) {
                parts.push(sizeCode.toUpperCase());
            }

            return parts.join(' - ');
        },

        onPcodeInput() {
            this.form.pcode = (this.form.pcode || '').toUpperCase();
            this.schedulePcodeLookup();
        },

        onPcodeBlur() {
            this.form.pcode = (this.form.pcode || '').toUpperCase().trim();
            this.lookupProductName();
        },

        schedulePcodeLookup() {
            if (this.pcodeLookupTimer) {
                clearTimeout(this.pcodeLookupTimer);
            }
            this.pcodeLookupTimer = setTimeout(() => this.lookupProductName(), 300);
        },

        async lookupProductName() {
            const pcode = (this.form.pcode || '').toUpperCase().trim();
            if (!pcode || pcode.length < 3) {
                return;
            }

            const current = (this.form.product_name || '').toUpperCase().trim();
            if (current !== '' && current !== this.autoFilledName) {
                return;
            }

            try {
                const url = `${this.pcodeNameUrl}?pcode=${encodeURIComponent(pcode)}&type=${this.itemType}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                if (data.found && data.product_name) {
                    this.form.product_name = data.product_name;
                    this.autoFilledName = (data.product_name || '').toUpperCase().trim();
                }
            } catch (e) {
                // Keep the field as-is when lookup fails.
            }
        },

        appendSizeSegment(base, sizeCode) {
            if (sizeCode === this.allSizeCode) {
                return base.toUpperCase();
            }

            return `${base}-${sizeCode}`.toUpperCase();
        },

        onType(e) {
            const opt = e.target.selectedOptions[0];
            this.typeCode = opt?.value ? (opt.dataset.code || '???') : '???';
        },

        onWarna(e) {
            const opt = e.target.selectedOptions[0];
            this.warnaCode = opt?.value ? (opt.dataset.code || '???') : '???';
        },

        onSize(e) {
            const opt = e.target.selectedOptions[0];
            this.sizeCode = opt?.value ? (opt.dataset.code || '???') : '???';
        },

        onWarnaMulti(e) {
            const container = e.target.closest('[class*="overflow-y-auto"]') || e.target.closest('div');
            this.warnaCodes = [...container.querySelectorAll('input[name="tags[warna][]"]:checked')]
                .map(i => ({ code: i.dataset.code || '???' }));
        },

        onSizeMulti(e) {
            const container = e.target.closest('[class*="overflow-y-auto"]') || e.target.closest('div');
            this.sizeCodes = [...container.querySelectorAll('input[name="tags[sizes][]"]:checked')]
                .map(i => ({ code: i.dataset.code || '???' }));
        },
    };
}
</script>
@endpush
