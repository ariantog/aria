@if($jubelioSync['show_ui'] ?? false)
<div class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm print:hidden"
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
    <span class="text-sm font-semibold text-gray-900">Sinkron Jubelio</span>

    @if(($jubelioSync['mapping_missing'] ?? 0) > 0)
    <span class="text-xs text-red-600">{{ $jubelioSync['mapping_missing'] }} item belum terhubung</span>
    @endif

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
                Adjust stock for <strong x-text="current.whName"></strong> in Jubelio?
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="submitAdjust()" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Confirm &amp; Push</button>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if(in_array($jubelioSync['sync_cek'], ['S', 'B'], true))
            @php
                $whName = $jubelioSync['wh_a_name'] ?: ($transaction->sender->name ?? '-');
                $needsSync = $jubelioSync['adjust_type_a'] > 0;
                $isSynced = (bool) $transaction->a_submit_by;
                $hasWarning = $jubelioSync['warning_a'] ?? false;
                $disabled = ($jubelioSync['mapping_missing'] ?? 0) > 0 || ! $jubelioSync['jubelio_a'];
            @endphp
            @if($isSynced)
                <span class="inline-flex items-center gap-1 rounded-md border border-green-500/30 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $whName }} synced
                </span>
            @elseif($hasWarning)
                <a href="{{ route('jubelio.transaction.detail-sync', $transaction) }}"
                   class="inline-flex items-center rounded-md border border-yellow-400 bg-yellow-50 px-3 py-1.5 text-xs font-medium text-yellow-800 hover:bg-yellow-100">
                    {{ $whName }} — perlu konfirmasi
                </a>
            @elseif($needsSync)
                <button type="button"
                        @click="openConfirm(1, 2, {{ $jubelioSync['adjust_type_a'] }}, @js($whName))"
                        @if($disabled) disabled @endif
                        class="inline-flex items-center rounded-md bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40">
                    Push to Jubelio — {{ $whName }}
                </button>
            @endif
        @endif

        @if(in_array($jubelioSync['sync_cek'], ['R', 'B'], true))
            @php
                $whName = $jubelioSync['wh_b_name'] ?: ($transaction->receiver->name ?? '-');
                $needsSync = $jubelioSync['adjust_type_b'] > 0;
                $isSynced = (bool) $transaction->b_submit_by;
                $hasWarning = $jubelioSync['warning_b'] ?? false;
                $disabled = ($jubelioSync['mapping_missing'] ?? 0) > 0 || ! $jubelioSync['jubelio_b'];
            @endphp
            @if($isSynced)
                <span class="inline-flex items-center gap-1 rounded-md border border-green-500/30 bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $whName }} synced
                </span>
            @elseif($hasWarning)
                <a href="{{ route('jubelio.transaction.detail-sync', $transaction) }}"
                   class="inline-flex items-center rounded-md border border-yellow-400 bg-yellow-50 px-3 py-1.5 text-xs font-medium text-yellow-800 hover:bg-yellow-100">
                    {{ $whName }} — perlu konfirmasi
                </a>
            @elseif($needsSync)
                <button type="button"
                        @click="openConfirm(2, 1, {{ $jubelioSync['adjust_type_b'] }}, @js($whName))"
                        @if($disabled) disabled @endif
                        class="inline-flex items-center rounded-md bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40">
                    Push to Jubelio — {{ $whName }}
                </button>
            @endif
        @endif
    </div>

    <a href="{{ route('jubelio.transaction.detail-sync', $transaction) }}"
       class="ml-auto text-xs font-medium text-blue-700 hover:underline">Detail</a>
</div>
@endif
