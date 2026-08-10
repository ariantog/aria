@if($jubelioSync['show_ui'] ?? false)
<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm print:hidden"
     x-data="{
        confirmOpen: false,
        current: { whName: '', side: '', whType: '', adjustType: '' },
        openConfirm(side, whType, adjustType, whName) {
            this.current = { side, whType, adjustType, whName };
            this.confirmOpen = true;
        },
        submitAdjust() {
            this.$refs.adjustForm.action = '{{ url('/jubelio-transaction') }}/' + '{{ $transaction->id }}' + '/adjust-stok';
            document.getElementById('tx_adjust_side').value = this.current.side;
            document.getElementById('tx_adjust_whType').value = this.current.whType;
            document.getElementById('tx_adjust_adjustType').value = this.current.adjustType;
            this.$refs.adjustForm.submit();
        }
     }">
    <div class="mb-4 flex items-center justify-between">
        <h2 class="flex items-center gap-2 text-lg font-semibold text-gray-900">
            <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Sinkron Jubelio
        </h2>
        <a href="{{ route('jubelio.transaction.detail-sync', $transaction) }}"
           class="text-xs font-medium text-blue-700 hover:underline">Halaman sinkron penuh</a>
    </div>

    <form method="POST" x-ref="adjustForm" class="hidden">
        @csrf
        <input type="hidden" name="side" id="tx_adjust_side">
        <input type="hidden" name="whType" id="tx_adjust_whType">
        <input type="hidden" name="adjustType" id="tx_adjust_adjustType">
    </form>

    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="confirmOpen = false">
        <div @click.away="confirmOpen = false" class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900">Push to Jubelio?</h3>
            <p class="mt-2 text-sm text-gray-500">
                Are you sure you want to adjust stock for <strong x-text="current.whName"></strong> in Jubelio?
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="submitAdjust()" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Confirm &amp; Push</button>
            </div>
        </div>
    </div>

    @if(($jubelioSync['mapping_missing'] ?? 0) > 0)
    <div class="mb-4 flex gap-3 rounded-xl border border-red-500/30 bg-red-50 p-4 text-red-600">
        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <p class="text-sm">Ada {{ $jubelioSync['mapping_missing'] }} item yang belum terhubung ke Jubelio. Hubungkan di menu Item sebelum push.</p>
    </div>
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @if(in_array($jubelioSync['sync_cek'], ['S', 'B'], true))
        @include('jubelio.partials.sync-card', [
            'title' => 'Sender (Side A)',
            'whName' => $jubelioSync['wh_a_name'] ?: ($transaction->sender->name ?? '-'),
            'jubName' => $jubelioSync['jubelio_a'],
            'type' => $jubelioSync['adjust_type_a'],
            'qty' => $transaction->total_items,
            'submittedBy' => $transaction->submitByA->username ?? null,
            'referenceId' => $transaction->a_reference_id,
            'needsSync' => $jubelioSync['adjust_type_a'] > 0,
            'disabled' => ($jubelioSync['mapping_missing'] ?? 0) > 0,
            'role' => 'sender',
            'side' => 1,
            'whType' => 2,
            'warning' => $jubelioSync['warning_a'],
            'transactionId' => $transaction->id,
        ])
        @endif
        @if(in_array($jubelioSync['sync_cek'], ['R', 'B'], true))
        @include('jubelio.partials.sync-card', [
            'title' => 'Receiver (Side B)',
            'whName' => $jubelioSync['wh_b_name'] ?: ($transaction->receiver->name ?? '-'),
            'jubName' => $jubelioSync['jubelio_b'],
            'type' => $jubelioSync['adjust_type_b'],
            'qty' => $transaction->total_items,
            'submittedBy' => $transaction->submitByB->username ?? null,
            'referenceId' => $transaction->b_reference_id,
            'needsSync' => $jubelioSync['adjust_type_b'] > 0,
            'disabled' => ($jubelioSync['mapping_missing'] ?? 0) > 0,
            'role' => 'receiver',
            'side' => 2,
            'whType' => 1,
            'warning' => $jubelioSync['warning_b'],
            'transactionId' => $transaction->id,
        ])
        @endif
    </div>
</div>
@endif
