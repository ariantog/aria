@php $multiSize = $multiSize ?? true; @endphp
@push('scripts')
<script>
function itemForm() {
    return {
        multiSize: @json($multiSize),
        form: {
            pcode: @js($formItem['pcode'] ?? old('pcode', '')),
            alias: @js($formItem['alias'] ?? old('alias', '')),
        },
        // selected code arrays for preview
        warnaCodes: [],   // [{code}]
        sizeCodes: [],    // [{code}]
        warnaCode: '???',
        sizeCode: '???',

        init() {
            this.$nextTick(() => this.syncFromDom());
        },

        syncFromDom() {
            const root = this.$root;
            // single selects (edit)
            const warnaSel = root.querySelector('select[name="tags[warna]"]');
            if (warnaSel && warnaSel.selectedOptions[0]) this.warnaCode = warnaSel.selectedOptions[0].dataset.code || '???';
            const sizeSel = root.querySelector('select[name="tags[sizes][]"]');
            if (sizeSel && sizeSel.selectedOptions[0]) this.sizeCode = sizeSel.selectedOptions[0].dataset.code || '???';
            // multi checkboxes (create/asset)
            const warnaChecks = root.querySelectorAll('input[name="tags[warna][]"]:checked');
            if (warnaChecks.length) this.warnaCodes = [...warnaChecks].map(i => ({ code: i.dataset.code }));
            const sizeChecks = root.querySelectorAll('input[name="tags[sizes][]"]:checked');
            if (sizeChecks.length) this.sizeCodes = [...sizeChecks].map(i => ({ code: i.dataset.code }));
        },

        get previewItems() {
            const pcode = (this.form.pcode || '').toUpperCase().trim();
            const alias = (this.form.alias || '').toUpperCase().trim() || '???';
            if (!pcode) return [];

            const items = [];
            if (this.multiSize) {
                // Create: cartesian of selected sizes x selected warnas
                const sizes = this.sizeCodes.length ? this.sizeCodes : [];
                const warnas = this.warnaCodes.length ? this.warnaCodes : [{ code: '???' }];
                if (sizes.length === 0) return [];
                sizes.forEach(s => {
                    warnas.forEach(w => {
                        const sc = (s.code || '???').toUpperCase();
                        const wc = (w.code || '???').toUpperCase();
                        items.push({
                            sku: `${pcode}-${wc}-${sc}`.toUpperCase(),
                            name: `${alias} - ${wc} - ${sc}`.toUpperCase(),
                        });
                    });
                });
            } else {
                // Edit: single row
                const wc = (this.warnaCode || '???').toUpperCase();
                const sc = (this.sizeCode || '???').toUpperCase();
                items.push({
                    sku: `${pcode}-${wc}-${sc}`.toUpperCase(),
                    name: `${alias} - ${wc} - ${sc}`.toUpperCase(),
                });
            }
            return items;
        },

        // single selects (edit)
        onWarna(e) {
            const opt = e.target.selectedOptions[0];
            this.warnaCode = opt ? (opt.dataset.code || '???') : '???';
        },
        onSize(e) {
            const opt = e.target.selectedOptions[0];
            this.sizeCode = opt ? (opt.dataset.code || '???') : '???';
        },

        // multi checkboxes (create/asset)
        onWarnaMulti(e) {
            this.warnaCodes = [...e.target.closest('div').querySelectorAll('input:checked')].map(i => ({ code: i.dataset.code }));
        },
        onSizeMulti(e) {
            this.sizeCodes = [...e.target.closest('div').querySelectorAll('input:checked')].map(i => ({ code: i.dataset.code }));
        },
    };
}
</script>
@endpush
