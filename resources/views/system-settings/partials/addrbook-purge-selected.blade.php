<div class="rounded-lg border border-blue-200 bg-blue-50/40 p-4" data-testid="addrbook-purge-selected-panel">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Selected addrbook</h3>
            <p class="mt-1 text-sm text-gray-700">
                <span class="font-medium">{{ $preview['name'] }}</span>
                <span class="text-gray-500">· {{ $preview['type_label'] }} #{{ $preview['id'] }}</span>
            </p>
        </div>
        @if($previewShowUrl)
        <a href="{{ $previewShowUrl }}"
           class="text-sm font-medium text-blue-600 hover:underline"
           data-testid="addrbook-purge-view-link">
            View addrbook →
        </a>
        @endif
    </div>

    <div class="mt-3">
        @if($preview['has_transactions'])
        <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700" data-testid="addrbook-purge-not-deletable">
            Present in transactions — cannot delete
        </span>
        @else
        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700" data-testid="addrbook-purge-deletable">
            No transactions — eligible for deletion
        </span>
        @endif
    </div>

    @if($preview['deletable'])
    <form method="POST"
          action="{{ route('data-retention.addrbook-purge.destroy') }}"
          id="addrbook-purge-form"
          data-testid="addrbook-purge-form"
          class="mt-4 space-y-4 border-t border-blue-200 pt-4"
          @submit="if (!markSubmitting() || ! confirm('Permanently delete {{ addslashes($preview['name']) }} ({{ $preview['type_label'] }} #{{ $preview['id'] }})?')) { $event.preventDefault(); submitting = false; }">
        @csrf
        <input type="hidden" name="addrbook_id" value="{{ $preview['id'] }}">
        @if($selectedType !== null)
        <input type="hidden" name="type" value="{{ $selectedType }}">
        @if($list)
        <input type="hidden" name="page" value="{{ $list->currentPage() }}">
        @endif
        @endif

        <div>
            <label for="addrbook-purge-confirm-text" class="mb-1 block text-sm font-medium text-gray-700">
                Type <code class="rounded bg-gray-100 px-1 text-xs">DELETE-ADDRBOOK</code> to confirm
            </label>
            <input type="text"
                   id="addrbook-purge-confirm-text"
                   data-testid="addrbook-purge-confirm-text"
                   name="confirm"
                   required
                   autocomplete="off"
                   class="h-9 w-full max-w-xs rounded-md border border-gray-300 bg-white px-2 text-sm text-gray-900">
        </div>

        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="checkbox"
                   id="addrbook-purge-confirm"
                   data-testid="addrbook-purge-confirm"
                   required
                   class="mt-0.5 rounded border-gray-300"
                   x-model="confirmed">
            <span>I understand this permanently deletes the addrbook and related pivot/stat rows.</span>
        </label>

        <button type="submit"
                id="addrbook-purge-submit"
                data-testid="addrbook-purge-submit"
                :disabled="!canSubmit()"
                :class="canSubmit() ? 'bg-red-600 hover:bg-red-700' : 'cursor-not-allowed bg-gray-300'"
                class="h-9 rounded-md px-4 text-sm font-medium text-white">
            Delete addrbook
        </button>
    </form>
    @endif
</div>
