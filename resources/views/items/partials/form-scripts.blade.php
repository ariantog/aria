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
        autoFilledPcode: '',
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
        tagFilters: {
            warna: '',
            type: '',
            size: '',
            jahit: '',
        },

        init() {
            this.autoFilledName = (this.form.product_name || '').toUpperCase().trim();
            this.autoFilledPcode = (this.form.pcode || '').toUpperCase().trim();
            this.$nextTick(() => this.syncFromDom());
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

        syncFromDom() {
            const root = this.$root;

            const typeInput = root.querySelector('input[name="tags[types]"]:checked, input[name="tags[types][]"]:checked');
            if (typeInput) {
                this.typeCode = typeInput.dataset.code || '???';
            }

            const warnaInput = root.querySelector('input[name="tags[warna]"]:checked');
            if (warnaInput) {
                this.warnaCode = warnaInput.dataset.code || '???';
            }

            const sizeInput = root.querySelector('input[name="tags[sizes][]"]:checked');
            if (sizeInput && !this.multiSize) {
                this.sizeCode = sizeInput.dataset.code || '???';
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

        applyTypePrefixToPcode(pcode, typeCode) {
            const tc = (typeCode || '').toUpperCase().trim();
            if (!tc || tc === '???') {
                return (pcode || '').toUpperCase().trim();
            }

            const trimmed = (pcode || '').toUpperCase().trim();
            if (!trimmed) {
                return trimmed;
            }

            const parts = trimmed.split('-');
            if (parts.length < 2) {
                return tc;
            }

            parts[0] = tc;

            return parts.join('-');
        },

        rewritePcodeFromType(typeCode) {
            if (!this.isAsset || !this.multiSize) {
                return;
            }

            const current = (this.form.pcode || '').toUpperCase().trim();
            if (current !== '' && current !== this.autoFilledPcode) {
                return;
            }

            const rewritten = this.applyTypePrefixToPcode(current, typeCode);
            this.form.pcode = rewritten;
            this.autoFilledPcode = rewritten;
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

        onTypeChange(e) {
            const input = e.target;
            this.typeCode = input?.checked ? (input.dataset.code || '???') : this.typeCode;
            if (this.isAsset) {
                this.rewritePcodeFromType(this.typeCode);
            }
        },

        onWarna(e) {
            const input = e.target;
            if (input?.checked) {
                this.warnaCode = input.dataset.code || '???';
            }
        },

        onSize(e) {
            const input = e.target;
            if (input?.checked) {
                this.sizeCode = input.dataset.code || '???';
            }
        },

        onWarnaMulti(e) {
            const list = this.$root.querySelector('[data-testid="tag-picker-warna"]');
            this.warnaCodes = list
                ? [...list.querySelectorAll('input[name="tags[warna][]"]:checked')].map(i => ({ code: i.dataset.code || '???' }))
                : [];
        },

        onSizeMulti(e) {
            const list = this.$root.querySelector('[data-testid="tag-picker-size"]');
            this.sizeCodes = list
                ? [...list.querySelectorAll('input[name="tags[sizes][]"]:checked')].map(i => ({ code: i.dataset.code || '???' }))
                : [];
        },
    };
}
</script>
@endpush
