@push('scripts')
<script>
function restockRecommendationsRestockEditor(initialRows) {
    const originals = {};
    const drafts = {};
    const editing = {};

    (initialRows || []).forEach(function (row) {
        const id = String(row.item_id);
        const qty = Math.max(0, parseInt(row.qty_restock, 10) || 0);
        originals[id] = qty;
        drafts[id] = qty;
    });

    return {
        originals: originals,
        drafts: drafts,
        editing: editing,
        itemKey: function (itemId) {
            return String(itemId);
        },
        formatQty: function (value) {
            const n = Math.max(0, parseInt(value, 10) || 0);
            return n.toLocaleString('en-US');
        },
        startEdit: function (itemId) {
            const key = this.itemKey(itemId);
            if (!(key in this.drafts)) {
                this.drafts[key] = this.originals[key] ?? 0;
            }
            this.editing[key] = true;
            this.$nextTick(function () {
                const input = document.querySelector('[data-testid="restock-recommendations-qty-' + itemId + '"]');
                if (input) {
                    input.focus();
                    input.select();
                }
            });
        },
        finishEdit: function (itemId) {
            this.onDraftInput(itemId);
            this.editing[this.itemKey(itemId)] = false;
        },
        isEditing: function (itemId) {
            return !!this.editing[this.itemKey(itemId)];
        },
        onDraftInput: function (itemId) {
            const key = this.itemKey(itemId);
            let value = parseInt(this.drafts[key], 10);
            if (!Number.isFinite(value) || value < 0) {
                value = 0;
            }
            this.drafts[key] = value;
        },
        isEdited: function (itemId) {
            const key = this.itemKey(itemId);
            return (this.drafts[key] ?? 0) !== (this.originals[key] ?? 0);
        },
        editedItemIdsList: function () {
            const ids = [];
            Object.keys(this.originals).forEach(function (key) {
                const draft = parseInt(this.drafts[key], 10) || 0;
                const original = parseInt(this.originals[key], 10) || 0;
                if (draft !== original) {
                    ids.push(parseInt(key, 10));
                }
            }.bind(this));
            return ids.sort(function (a, b) { return a - b; });
        },
        hasPendingEdits: function () {
            return this.editedItemIdsList().length > 0;
        },
        editCount: function () {
            return this.editedItemIdsList().length;
        },
        draftQty: function (itemId) {
            return this.drafts[this.itemKey(itemId)] ?? 0;
        },
    };
}
</script>
@endpush
