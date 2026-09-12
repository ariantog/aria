@php
    $multiSize = $multiSize ?? true;
    $isAsset = $isAsset ?? false;
    $formItem = $formItem ?? [
        'pcode' => old('pcode', ''),
        'product_name' => old('product_name', ''),
        'price' => old('price', ''),
        'cost' => old('cost', ''),
        'reseller_price' => old('reseller_price', ''),
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
        autoFilledShared: {
            description: '',
            description2: '',
            url: '',
            reseller_price: '',
        },
        pcodeLookupTimer: null,
        form: {
            pcode: @js($formItem['pcode'] ?? ''),
            product_name: @js($formItem['product_name'] ?? ''),
        },
        defaultPrice: @js($formItem['price'] ?? ''),
        defaultCost: @js($formItem['cost'] ?? ''),
        defaultResellerPrice: @js($formItem['reseller_price'] ?? ''),
        skuOverrides: @js(old('sku_overrides', [])),
        typeCode: '???',
        warnaCode: '???',
        warnaName: '???',
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
            this.captureAutoFilledSharedFromDom();
            this.$nextTick(() => {
                this.syncFromDom();
                this.schedulePcodeLookup();
            });
        },

        sharedFieldValue(fieldId) {
            const el = document.getElementById(fieldId);
            return el ? String(el.value || '').trim() : '';
        },

        setSharedFieldValue(fieldId, value) {
            const el = document.getElementById(fieldId);
            if (el) {
                el.value = value ?? '';
            }
        },

        captureAutoFilledSharedFromDom() {
            this.autoFilledShared = {
                description: this.sharedFieldValue('item-form-description'),
                description2: this.sharedFieldValue('item-form-description2'),
                url: this.sharedFieldValue('item-form-url'),
                reseller_price: this.sharedFieldValue('item-form-reseller-price'),
            };
        },

        canAutoFillSharedField(fieldId, key) {
            const current = this.sharedFieldValue(fieldId);
            const auto = String(this.autoFilledShared[key] ?? '').trim();

            return current === '' || current === auto;
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
                this.warnaName = warnaInput.dataset.name || this.warnaCode;
            }

            const sizeInput = root.querySelector('input[name="tags[sizes][]"]:checked');
            if (sizeInput && !this.multiSize) {
                this.sizeCode = sizeInput.dataset.code || '???';
            }

            const warnaChecks = root.querySelectorAll('input[name="tags[warna][]"]:checked');
            if (warnaChecks.length) {
                this.warnaCodes = [...warnaChecks].map(i => ({
                    code: i.dataset.code || '???',
                    name: i.dataset.name || i.dataset.code || '???',
                }));
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
                        const wn = (w.name || wc).toUpperCase();
                        const sku = this.appendSizeSegment(`${pcode}-${wc}`, sc);
                        appendRow(sku, this.buildDisplayName(productName, wn, sc));
                    });
                });

                return items;
            }

            if (this.multiSize) {
                const sizes = this.sizeCodes;
                const wc = (this.warnaCode || '???').toUpperCase();
                const wn = (this.warnaName || wc).toUpperCase();
                const tc = (this.typeCode || '???').toUpperCase();
                if (!sizes.length || (!this.isAsset && tc === '???')) {
                    return [];
                }
                sizes.forEach(s => {
                    const sc = (s.code || '???').toUpperCase();
                    const sku = this.isAsset
                        ? this.appendSizeSegment(`${pcode}-${wc}`, sc)
                        : this.appendSizeSegment(`${tc}-${pcode}`, sc);
                    appendRow(sku, this.buildDisplayName(productName, wn, sc));
                });

                return items;
            }

            const wc = (this.warnaCode || '???').toUpperCase();
            const wn = (this.warnaName || wc).toUpperCase();
            const sc = (this.sizeCode || '???').toUpperCase();
            const tc = (this.typeCode || '???').toUpperCase();
            const sku = this.isAsset
                ? this.appendSizeSegment(`${pcode}-${wc}`, sc)
                : this.appendSizeSegment(`${tc}-${pcode}`, sc);
            appendRow(sku, this.buildDisplayName(productName, wn, sc));

            return items;
        },

        buildDisplayName(productName, warnaLabel, sizeCode) {
            let title = (productName || '').toUpperCase().trim();
            const warna = (warnaLabel || '').toUpperCase().trim();
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

        normalizeManufacturedPcode(value) {
            return String(value || '').toUpperCase().trim().replace(/\//g, '-');
        },

        manufacturedParentMaster(pcode) {
            const normalized = this.normalizeManufacturedPcode(pcode);
            const colorway = normalized.match(/^([A-Z]{2,3}\d{5})-\d{2,3}$/);

            if (colorway) {
                return colorway[1];
            }

            if (/^[A-Z]{2,3}\d{5}$/.test(normalized)) {
                return normalized;
            }

            return '';
        },

        productNameIsPcodePlaceholder(name, pcode) {
            const nameNorm = this.normalizeManufacturedPcode(name).replace(/\s/g, '');
            const pcodeNorm = this.normalizeManufacturedPcode(pcode).replace(/\s/g, '');

            if (nameNorm === '') {
                return true;
            }

            if (pcodeNorm !== '' && nameNorm === pcodeNorm) {
                return true;
            }

            const parent = this.manufacturedParentMaster(pcode);

            return parent !== '' && nameNorm === parent;
        },

        canAutoFillProductName() {
            if (!this.isAsset) {
                return this.productNameIsPcodePlaceholder(this.form.product_name, this.form.pcode)
                    || (this.form.product_name || '').toUpperCase().trim() === this.autoFilledName;
            }

            const name = (this.form.product_name || '').toUpperCase().trim();
            const pcode = (this.form.pcode || '').toUpperCase().trim();

            if (name === '') {
                return true;
            }

            if (name === this.autoFilledName) {
                return true;
            }

            return pcode !== '' && name === pcode;
        },

        onPcodeInput() {
            const previousPcode = (this.autoFilledPcode || '').toUpperCase().trim();
            if (this.isAsset) {
                this.form.pcode = (this.form.pcode || '').toUpperCase();
            } else {
                this.form.pcode = this.normalizeManufacturedPcode(this.form.pcode);
            }
            const pcode = (this.form.pcode || '').toUpperCase().trim();

            if (previousPcode !== '' && pcode !== previousPcode && this.canAutoFillProductName()) {
                if (this.productNameIsPcodePlaceholder(this.form.product_name, previousPcode)
                    || this.productNameIsPcodePlaceholder(this.form.product_name, pcode)) {
                    this.form.product_name = '';
                    this.autoFilledName = '';
                }
            }

            this.autoFilledPcode = pcode;
            this.schedulePcodeLookup();
        },

        onPcodeBlur() {
            if (this.isAsset) {
                this.form.pcode = (this.form.pcode || '').toUpperCase().trim();
            } else {
                this.form.pcode = this.normalizeManufacturedPcode(this.form.pcode);
            }
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

            if (!this.canAutoFillProductName()) {
                return;
            }

            try {
                const typeCode = (this.typeCode || '').toUpperCase().trim();
                const typeCodeParam = typeCode && typeCode !== '???'
                    ? `&type_code=${encodeURIComponent(typeCode)}`
                    : '';
                const url = `${this.pcodeNameUrl}?pcode=${encodeURIComponent(pcode)}&type=${this.itemType}${typeCodeParam}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                const data = await res.json();
                if (!data.found) {
                    return;
                }

                if (data.product_name && this.canAutoFillProductName()) {
                    this.form.product_name = data.product_name;
                    this.autoFilledName = (data.product_name || '').toUpperCase().trim();
                }

                if (this.canAutoFillSharedField('item-form-description', 'description') && data.description) {
                    this.setSharedFieldValue('item-form-description', data.description);
                    this.autoFilledShared.description = String(data.description).trim();
                }

                if (this.canAutoFillSharedField('item-form-description2', 'description2') && data.description2) {
                    this.setSharedFieldValue('item-form-description2', data.description2);
                    this.autoFilledShared.description2 = String(data.description2).trim();
                }

                if (this.canAutoFillSharedField('item-form-url', 'url') && data.url) {
                    this.setSharedFieldValue('item-form-url', data.url);
                    this.autoFilledShared.url = String(data.url).trim();
                }

                if (this.isAsset && this.canAutoFillSharedField('item-form-reseller-price', 'reseller_price')) {
                    const resellerPrice = data.reseller_price;
                    if (resellerPrice !== null && resellerPrice !== undefined && String(resellerPrice) !== '' && Number(resellerPrice) > 0) {
                        this.setSharedFieldValue('item-form-reseller-price', resellerPrice);
                        this.autoFilledShared.reseller_price = String(resellerPrice).trim();
                    }
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
                this.warnaName = input.dataset.name || this.warnaCode;
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
                ? [...list.querySelectorAll('input[name="tags[warna][]"]:checked')].map(i => ({
                    code: i.dataset.code || '???',
                    name: i.dataset.name || i.dataset.code || '???',
                }))
                : [];
        },

        onSizeMulti(e) {
            const list = this.$root.querySelector('[data-testid="tag-picker-size"]');
            this.sizeCodes = list
                ? [...list.querySelectorAll('input[name="tags[sizes][]"]:checked')].map(i => ({ code: i.dataset.code || '???' }))
                : [];
        },

        skuOverrideRow(sku) {
            const key = String(sku || '').toUpperCase();
            return this.skuOverrides[key] || this.skuOverrides[sku] || {};
        },

        skuOverrideValue(sku, field) {
            const row = this.skuOverrideRow(sku);
            return row[field] ?? '';
        },

        previewOverrideOpen(sku) {
            const row = this.skuOverrideRow(sku);

            return ['price', 'cost', 'reseller_price', 'description', 'description2']
                .some((field) => String(row[field] ?? '').trim() !== '');
        },
    };
}
</script>
@endpush
