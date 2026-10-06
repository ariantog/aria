@php
    $status = $cashInLinkStatus ?? $import->cashInLinkStatus();
@endphp
@if(($canUnlinkCashIn ?? false) && $status !== 'none')
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950" data-testid="faktur-cash-in-unlink-banner">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if($status === 'live' && $import->cashInTransaction)
                    <p class="font-medium">Cash In #{{ $import->cashInTransaction->id }} ter-link ke faktur ini.</p>
                    <p class="text-xs text-amber-900/80">Lepas link jika ingin buat atau link Cash In lain. Transaksi Cash In tidak ikut terhapus.</p>
                @else
                    <p class="font-medium">Link Cash In #{{ $import->cash_in_transaction_id }} tidak valid (transaksi sudah dihapus atau bukan Cash In).</p>
                    <p class="text-xs text-amber-900/80">Bersihkan link agar form <strong>Buat Cash In dari faktur</strong> muncul lagi.</p>
                @endif
            </div>
            <form method="POST" action="{{ route('reports.tax.faktur.cash-in.unlink', $import) }}" class="shrink-0"
                  onsubmit="return confirm(@js($status === 'live'
                      ? 'Lepas link Cash In dari faktur ini? Transaksi Cash In tidak dihapus.'
                      : 'Bersihkan link Cash In yang sudah tidak ada?'))">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-amber-100"
                        data-testid="{{ $status === 'live' ? 'faktur-unlink-cash-in' : 'faktur-clear-stale-cash-in' }}">
                    {{ $status === 'live' ? 'Lepas link Cash In' : 'Bersihkan link' }}
                </button>
            </form>
        </div>
    </div>
@endif
