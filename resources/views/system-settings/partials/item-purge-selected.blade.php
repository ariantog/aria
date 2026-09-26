<div class="rounded-lg border border-blue-200 bg-blue-50/40 p-4" data-testid="item-purge-selected-panel">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Selected item</h3>
            <p class="mt-1 text-sm text-gray-700">
                <span class="font-mono text-xs font-medium">{{ $preview['code'] }}</span>
                <span class="text-gray-500">· {{ $preview['name'] }}</span>
                <span class="text-gray-500">· {{ $preview['type_label'] }} #{{ $preview['id'] }}</span>
            </p>
        </div>
        @if($previewShowUrl)
        <a href="{{ $previewShowUrl }}"
           class="text-sm font-medium text-blue-600 hover:underline"
           data-testid="item-purge-view-link">
            View item →
        </a>
        @endif
    </div>

    <p class="mt-2 text-xs text-gray-600">
        Warehouse qty: <span class="tabular-nums font-medium">{{ number_format($preview['warehouse_qty'], 0, ',', '.') }}</span>
        @if($preview['deleted_at'])
        · Soft deleted {{ \Illuminate\Support\Carbon::parse($preview['deleted_at'])->format('Y-m-d') }}
        @endif
    </p>

    <div class="mt-3">
        @if($preview['has_transaction_details'])
        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700" data-testid="item-purge-not-deletable">
            Present in transaction details — cannot delete
        </span>
        @else
        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" data-testid="item-purge-deletable">
            No transaction details — eligible for deletion
        </span>
        @endif
    </div>

    @if($preview['deletable'])
    <form method="POST"
          action="{{ route('data-retention.item-purge.destroy') }}"
          id="item-purge-form"
          data-testid="item-purge-form"
          class="mt-4 space-y-4 border-t border-blue-200 pt-4"
          x-data="{
              confirmed: false,
              submitting: false,
              canSubmit() {
                  return this.confirmed && !this.submitting;
              },
              markSubmitting() {
                  if (!this.canSubmit()) {
                      return false;
                  }
                  this.submitting = true;
                  return true;
              }
          }"
          @submit="if (!markSubmitting() || ! confirm('Permanently delete {{ addslashes($preview['code']) }} ({{ $preview['type_label'] }} #{{ $preview['id'] }})?')) { $event.preventDefault(); submitting = false; }">
        @csrf
        <input type="hidden" name="item_id" value="{{ $preview['id'] }}">
        @if(isset($maxId) && $maxId > 0)
        <input type="hidden" name="max_id" value="{{ $maxId }}">
        @endif
        @if(isset($itemType) && $itemType !== null)
        <input type="hidden" name="item_type" value="{{ $itemType }}">
        @endif
        @if(isset($previewPage) && $previewPage > 1)
        <input type="hidden" name="page" value="{{ $previewPage }}">
        @endif

        <div>
            <label for="item-purge-confirm-text" class="mb-1 block text-sm font-medium text-gray-700">
                Type <code class="rounded bg-gray-100 px-1 text-xs">DELETE-ITEM</code> to confirm
            </label>
            <input type="text"
                   id="item-purge-confirm-text"
                   data-testid="item-purge-confirm-text"
                   name="confirm"
                   required
                   autocomplete="off"
                   class="h-9 w-full max-w-xs rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900">
        </div>

        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox"
                   id="item-purge-confirm"
                   data-testid="item-purge-confirm"
                   required
                   class="mt-0.5 rounded border-gray-300"
                   x-model="confirmed">
            <span>I understand this permanently deletes the item, warehouse stock rows, stats, and related pivot data.</span>
        </label>

        <button type="submit"
                id="item-purge-submit"
                data-testid="item-purge-submit"
                :disabled="!canSubmit()"
                :class="canSubmit() ? 'bg-red-600 hover:bg-red-700' : 'cursor-not-allowed bg-gray-300'"
                class="h-9 rounded-md px-4 text-sm font-medium text-white">
            Delete item
        </button>
    </form>
    @endif
</div>
