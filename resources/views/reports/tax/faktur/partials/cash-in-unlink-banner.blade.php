@php
    $mode = $paymentBannerMode ?? $import->paymentBannerMode();
@endphp
@if(($canUnlinkCashIn ?? false) && $mode !== 'none')
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" data-testid="faktur-cash-in-unlink-banner">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if($mode === 'live' && $import->cashInTransaction)
                    <p class="font-medium">Cash In #{{ $import->cashInTransaction->id }} ter-link ke faktur ini.</p>
                    <p class="text-xs text-amber-900/80">Lepas link jika ingin buat atau link Cash In lain. Transaksi Cash In tidak ikut terhapus.</p>
                @elseif($mode === 'stale')
                    <p class="font-medium">Link Cash In #{{ $import->cash_in_transaction_id }} tidak valid (transaksi sudah dihapus atau bukan Cash In).</p>
                    <p class="text-xs text-amber-900/80">Bersihkan link agar form <strong>Buat Cash In dari faktur</strong> bisa dipakai lagi.</p>
                @else
                    <p class="font-medium">Data pembayaran masih tersimpan di faktur, tapi Cash In tidak ter-link.</p>
                    <p class="text-xs text-amber-900/80">
                        @if($import->payment_received_amount !== null)
                            Jumlah/tanggal bayar masih terisi
                            @if($import->variance_transaction_id)
                                · variance tx #{{ $import->variance_transaction_id }} masih terreferensi
                            @endif
                            — reset hanya di record faktur (transaksi di ledger tidak dihapus).
                        @else
                            Referensi transaksi selisih masih ada — reset record faktur untuk posting Cash In baru.
                        @endif
                    </p>
                @endif
            </div>
            @php
                $resetConfirm = match ($mode) {
                    'live' => 'Lepas link Cash In dari faktur ini? Transaksi Cash In tidak dihapus.',
                    'stale' => 'Bersihkan link Cash In yang sudah tidak ada?',
                    default => 'Reset data pembayaran di faktur ini? Transaksi Cash In / variance di ledger tidak dihapus.',
                };
            @endphp
            <form method="POST" action="{{ route('reports.tax.faktur.cash-in.unlink', $import) }}" class="shrink-0"
                  onsubmit="return confirm(@js($resetConfirm))">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-amber-100"
                        data-testid="{{ match ($mode) {
                            'live' => 'faktur-unlink-cash-in',
                            'stale' => 'faktur-clear-stale-cash-in',
                            default => 'faktur-reset-payment-snapshot',
                        } }}">
                    {{ match ($mode) {
                        'live' => 'Lepas link Cash In',
                        'stale' => 'Bersihkan link',
                        default => 'Reset data pembayaran',
                    } }}
                </button>
            </form>
        </div>
    </div>
@endif
