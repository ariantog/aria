{{--
    Shared transactions list table (used by /transactions and by a contact's
    transactions page) so there is one design to maintain.

    Params:
      $rows        - paginator of Transaction (with sender, receiver loaded)
      $can         - permissions array (bank_hidden_balance, delete_transaction, edit_transaction) — optional
      $sortLink    - closure(column) => url for sortable headers — optional (plain headers if null)
      $sort        - current sort column — optional
      $direction   - current sort direction (asc/desc) — optional
      $highlightId - addrbook id to bold in sender/receiver (the contact being viewed) — optional
--}}
@php
    $can = $can ?? [];
    $sortLink = $sortLink ?? null;
    $sort = $sort ?? null;
    $direction = $direction ?? null;
    $highlightId = $highlightId ?? null;
    $hideBank = $can['bank_hidden_balance'] ?? false;
    $canEditNote = $can['edit_transaction'] ?? false;
    $typeMap = [
        1  => ['Buy',           'text-emerald-700 bg-emerald-50', 'bg-emerald-500'],
        2  => ['Sell',          'text-blue-700 bg-blue-50',       'bg-blue-500'],
        3  => ['Move',          'text-amber-700 bg-amber-50',     'bg-amber-500'],
        6  => ['Transfer',      'text-cyan-700 bg-cyan-50',       'bg-cyan-500'],
        7  => ['Cash Out',      'text-rose-700 bg-rose-50',       'bg-rose-500'],
        8  => ['Use',           'text-yellow-700 bg-yellow-50',   'bg-yellow-500'],
        9  => ['Cash In',       'text-purple-700 bg-purple-50',   'bg-purple-500'],
        12 => ['Adjust',        'text-indigo-700 bg-indigo-50',   'bg-indigo-500'],
        15 => ['Return',        'text-rose-700 bg-rose-50',       'bg-rose-500'],
        16 => ['Production',    'text-slate-700 bg-slate-50',     'bg-slate-500'],
        17 => ['Ret. Supplier', 'text-orange-700 bg-orange-50',   'bg-orange-500'],
        18 => ['Depreciation',  'text-zinc-700 bg-zinc-50',       'bg-zinc-500'],
    ];
@endphp

<div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
     x-data="transactionListTable()">
    <div class="flex items-center justify-end border-b border-gray-100 px-3 py-2">
        <button type="button"
                @click="copyRowsTable()"
                data-testid="copy-transactions-table"
                title="Copy table for Excel"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span x-text="copyFeedback ? 'Copied!' : 'Copy rows'"></span>
        </button>
    </div>
    <div class="overflow-x-auto">
        <table x-ref="listTable" class="w-full min-w-[980px] text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="date">
                        @if($sortLink)<a href="{{ $sortLink('date') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Date @if($sort==='date')<span class="text-blue-600">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</a>@else Date @endif
                    </th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="type">
                        @if($sortLink)<a href="{{ $sortLink('type') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Type @if($sort==='type')<span class="text-blue-600">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</a>@else Type @endif
                    </th>
                    <th class="px-3 py-2.5 text-left font-medium" data-copy-col="invoice">
                        @if($sortLink)<a href="{{ $sortLink('invoice') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Invoice @if($sort==='invoice')<span class="text-blue-600">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</a>@else Invoice @endif
                    </th>
                    <th class="hidden px-3 py-2.5 text-left font-medium md:table-cell" data-copy-col="description">Description</th>
                    <th class="px-3 py-2.5 text-right font-medium" data-copy-col="total">
                        @if($sortLink)<a href="{{ $sortLink('total') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Total @if($sort==='total')<span class="text-blue-600">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</a>@else Total @endif
                    </th>
                    <th class="px-3 py-2.5 text-right font-medium" data-copy-col="total_items">
                        @if($sortLink)<a href="{{ $sortLink('total_items') }}" class="inline-flex items-center gap-1 hover:text-gray-900">Total Items @if($sort==='total_items')<span class="text-blue-600">{{ $direction==='asc'?'↑':'↓' }}</span>@endif</a>@else Total Items @endif
                    </th>
                    <th class="hidden px-3 py-2.5 text-left font-medium lg:table-cell" data-copy-col="sender">Sender</th>
                    <th class="hidden px-3 py-2.5 text-left font-medium lg:table-cell" data-copy-col="receiver">Receiver</th>
                    <th class="w-12 px-3 py-2.5 text-center font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $tx)
                    @php
                        $typeValue = (int) $tx->type;
                        [$label, $badgeCls, $dotCls] = $typeMap[$typeValue] ?? ['Unknown', 'text-gray-700 bg-gray-50', 'bg-gray-400'];
                        $noteText = $tx->description ?: ($tx->notes ?: '-');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600" data-copy-col="date">
                            {{ $tx->date ? \Carbon\Carbon::parse($tx->date)->format('d/m/y') : '-' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5" data-copy-col="type">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeCls }}">
                                <span class="h-1.5 w-1.5 flex-shrink-0 rounded-full {{ $dotCls }}"></span>{{ $label }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5" data-copy-col="invoice">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $tx->invoice ?: '—' }}</a>
                            <div class="mt-0.5 text-xs text-gray-400 lg:hidden">{{ $tx->sender->name ?? '—' }} → {{ $tx->receiver->name ?? '—' }}</div>
                        </td>
                        <td class="hidden max-w-[200px] px-3 py-2.5 md:table-cell" data-copy-col="description">
                            <div class="flex items-start gap-1">
                                <span data-tx-note="{{ $tx->id }}" class="min-w-0 flex-1 truncate text-xs leading-tight text-gray-500" title="{{ $noteText !== '-' ? $noteText : '' }}">{{ $noteText }}</span>
                                @if($canEditNote)
                                    <button type="button"
                                            data-testid="edit-tx-note-{{ $tx->id }}"
                                            @click="openNoteEdit({{ $tx->id }}, @js($noteText !== '-' ? $noteText : ''))"
                                            class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-blue-600"
                                            title="Edit description">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums text-gray-900" data-copy-col="total" data-copy-value="{{ format_copy_number($tx->displaySignedGrandTotal()) }}" data-testid="tx-list-total-{{ $tx->id }}">
                            {{ format_amount($tx->displaySignedGrandTotal()) }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-gray-500" data-copy-col="total_items" data-copy-value="{{ format_copy_number($tx->total_items) }}">
                            {{ format_amount($tx->total_items) }}
                        </td>
                        <td class="hidden max-w-[180px] px-3 py-2.5 lg:table-cell" data-copy-col="sender">
                            @if($tx->sender)
                                <a href="{{ url('/'.$tx->sender->type_slug.'/'.$tx->sender->id) }}" class="block truncate hover:underline {{ $highlightId && $tx->sender->id === $highlightId ? 'font-bold text-blue-700' : 'text-blue-600' }}">{{ $tx->sender->name }}</a>
                                @unless($hideBank && $tx->sender->type_slug === 'bank')
                                    <span class="text-xs tabular-nums {{ (float) $tx->sender_balance < 0 ? 'text-rose-500' : 'text-gray-400' }}">{{ format_amount($tx->sender_balance) }}</span>
                                @endunless
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="hidden max-w-[180px] px-3 py-2.5 lg:table-cell" data-copy-col="receiver">
                            @if($tx->receiver)
                                <a href="{{ url('/'.$tx->receiver->type_slug.'/'.$tx->receiver->id) }}" class="block truncate hover:underline {{ $highlightId && $tx->receiver->id === $highlightId ? 'font-bold text-blue-700' : 'text-blue-600' }}">{{ $tx->receiver->name }}</a>
                                @unless($hideBank && $tx->receiver->type_slug === 'bank')
                                    <span class="text-xs tabular-nums {{ (float) $tx->receiver_balance < 0 ? 'text-rose-500' : 'text-gray-400' }}">{{ format_amount($tx->receiver_balance) }}</span>
                                @endunless
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <div class="relative inline-block" x-data="{ open: false }">
                                <button @click="open = !open" class="flex h-7 w-7 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zM12 10a2 2 0 11-4 0 2 2 0 014 0zM16 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                                </button>
                                <div x-show="open" x-cloak @click.away="open = false" class="absolute right-0 top-8 z-30 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                                    <a href="{{ route('transactions.show', $tx->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        View
                                    </a>
                                    @if($canEditNote)
                                        <button type="button"
                                                @click="open = false; openNoteEdit({{ $tx->id }}, @js($noteText !== '-' ? $noteText : ''))"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Edit Note
                                        </button>
                                    @endif
                                    @if($can['delete_transaction'] ?? false)
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('transactions.destroy', $tx->id) }}" onsubmit="return confirm('Delete this transaction? Stock and balance impact will be reversed.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-16 text-center">
                            <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <p class="mt-2 text-sm text-gray-400">No transactions found.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pagination', ['paginator' => $rows, 'label' => 'transactions'])

    @if($canEditNote)
    <div x-show="noteModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.window.escape="noteModalOpen = false">
        <div @click.away="noteModalOpen = false" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900">Edit Description</h3>
            <p class="mt-1 text-sm text-gray-500">Update the transaction note shown in the list.</p>
            <div class="mt-4">
                <label for="tx-note-textarea" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                <textarea id="tx-note-textarea" x-ref="noteTextarea" x-model="noteText" rows="4"
                          class="w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                          placeholder="Add a note…"></textarea>
                <p x-show="noteError" x-text="noteError" class="mt-2 text-sm text-rose-600"></p>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="noteModalOpen = false"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="saveNote()" :disabled="noteSaving"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                    <span x-show="!noteSaving">Save</span>
                    <span x-show="noteSaving">Saving…</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

@once
    @push('scripts')
    <script>
    function transactionListTable() {
        return {
            noteModalOpen: false,
            noteTxId: null,
            noteText: '',
            noteSaving: false,
            noteError: '',
            copyFeedback: false,
            copyFeedbackTimer: null,
            openNoteEdit(id, text) {
                this.noteTxId = id;
                this.noteText = (text && text !== '-') ? text : '';
                this.noteError = '';
                this.noteModalOpen = true;
                this.$nextTick(() => this.$refs.noteTextarea?.focus());
            },
            async saveNote() {
                if (!this.noteTxId || this.noteSaving) {
                    return;
                }
                this.noteSaving = true;
                this.noteError = '';
                try {
                    const res = await fetch(`/transactions/${this.noteTxId}/note`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ note: this.noteText }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.noteError = data.message || (data.errors?.note?.[0] ?? 'Failed to save note.');
                        return;
                    }
                    const cell = document.querySelector(`[data-tx-note='${this.noteTxId}']`);
                    if (cell) {
                        cell.textContent = data.display ?? '-';
                    }
                    this.noteModalOpen = false;
                } catch (e) {
                    this.noteError = 'Failed to save note.';
                } finally {
                    this.noteSaving = false;
                }
            },
            showCopyFeedback() {
                this.copyFeedback = true;
                clearTimeout(this.copyFeedbackTimer);
                this.copyFeedbackTimer = setTimeout(() => {
                    this.copyFeedback = false;
                }, 2000);
            },
            isCopyColumnVisible(col) {
                return true;
            },
            async copyRowsTable() {
                if (await ariaCopyTable(this.$refs.listTable, (col) => this.isCopyColumnVisible(col))) {
                    this.showCopyFeedback();
                }
            },
        };
    }
    </script>
    @endpush
@endonce
