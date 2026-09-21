@if($addrbook->trashed())
<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p>
            This contact was deleted
            @if($addrbook->deleted_at)
                on {{ $addrbook->deleted_at->translatedFormat('d F Y H:i') }}
            @endif
            . Restore it to use it in new transactions.
        </p>
        @if(($canRestore ?? false) || (auth()->user()?->can(\App\Models\Addrbook::getPermissions($addrbook->type_slug)['edit']) ?? false))
        <form method="POST" action="{{ route('addrbook.restore', $addrbook) }}" class="shrink-0">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Restore
            </button>
        </form>
        @endif
    </div>
</div>
@endif
