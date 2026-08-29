@extends('layouts.app')

@section('title', 'Sync Detail #' . $data->invoice)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Transaction Sync', 'href' => route('jubelio.transaction.sync')],
    ['title' => 'Sync Detail #' . ($data->invoice ?? $data->id)],
];
$mappingMissing = $data->item_with_jubelio_count > 0;
@endphp

<div class="flex flex-col gap-6 p-6"
     x-data="{
        confirmOpen: false,
        current: { whName: '', side: '', whType: '', adjustType: '' },
        openConfirm(side, whType, adjustType, whName) {
            this.current = { side, whType, adjustType, whName };
            this.confirmOpen = true;
        },
        submitAdjust() {
            this.$refs.adjustForm.action = '{{ url('/jubelio-transaction') }}/' + '{{ $data->id }}' + '/adjust-stok';
            document.getElementById('adjust_side').value = this.current.side;
            document.getElementById('adjust_whType').value = this.current.whType;
            document.getElementById('adjust_adjustType').value = this.current.adjustType;
            this.$refs.adjustForm.submit();
        }
     }">

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('jubelio.transaction.sync') }}" class="flex h-9 w-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="text-2xl font-bold">Transaction Detail #{{ $data->id }}</h1>
        </div>
    </div>

    {{-- hidden adjust form (used by all Push buttons) --}}
    <form method="POST" x-ref="adjustForm" class="hidden">
        @csrf
        <input type="hidden" name="side" id="adjust_side">
        <input type="hidden" name="whType" id="adjust_whType">
        <input type="hidden" name="adjustType" id="adjust_adjustType">
    </form>

    {{-- Confirm dialog --}}
    <div x-show="confirmOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="confirmOpen = false">
        <div @click.away="confirmOpen = false" class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-gray-900">Push to Jubelio?</h3>
            <p class="mt-2 text-sm text-gray-500">
                Are you sure you want to adjust stock for <strong x-text="current.whName"></strong> in Jubelio?
                This action will create a stock adjustment in your Jubelio account.
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="confirmOpen = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" @click="submitAdjust()" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Confirm &amp; Push</button>
            </div>
        </div>
    </div>

    @if(!$can_sync)
    <div class="flex items-center gap-3 rounded-xl border border-blue-500/20 bg-blue-50 p-6 text-blue-700 shadow-sm">
        <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <h3 class="font-bold">Transaksi Otomatis</h3>
            <p class="text-sm opacity-80">Transaksi ini dikirimkan secara otomatis oleh sistem (Cron/Jubelio). Sinkronisasi manual tidak diperlukan dan tidak dapat dilakukan untuk data ini.</p>
        </div>
    </div>
    @elseif(!($show_ui ?? false))
    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-6 text-gray-700 shadow-sm">
        <svg class="h-6 w-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <h3 class="font-bold">Integrasi Jubelio Nonaktif</h3>
            <p class="text-sm opacity-80">Push stok ke Jubelio dinonaktifkan saat <code class="rounded bg-gray-200 px-1">JUBELIO_ACTIVE=false</code>. Aktifkan integrasi di environment untuk menampilkan tombol sinkron.</p>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="space-y-6 md:col-span-1">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <div class="mb-6 flex items-start justify-between">
                    <div>
                        <p class="mb-1 text-xs font-bold uppercase text-gray-400">Invoice</p>
                        <p class="text-lg font-bold">{{ $data->invoice }}</p>
                    </div>
                    <div class="text-center">
                        <p class="mb-1 text-xs font-bold uppercase text-gray-400">Date</p>
                        <p class="text-sm font-bold">{{ \Carbon\Carbon::parse($data->date)->translatedFormat('d M Y') }}</p>
                    </div>
                    <div class="text-right">
                        <p class="mb-1 text-xs font-bold uppercase text-gray-400">Total</p>
                        <p class="text-lg font-bold text-blue-600">Rp {{ format_amount($data->total, 0) }}</p>
                    </div>
                </div>

                <div class="space-y-4 border-t border-gray-100 pt-4">
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase text-gray-400">From</p>
                        <p class="text-sm font-medium">{{ $data->sender->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase text-gray-400">To</p>
                        <p class="text-sm font-medium">{{ $data->receiver->name ?? '-' }}</p>
                    </div>
                    @if($data->description)
                    <div>
                        <p class="mb-1 text-[10px] font-bold uppercase text-gray-400">Note</p>
                        <p class="text-xs italic text-gray-500">{{ $data->description }}</p>
                    </div>
                    @endif
                </div>
            </div>

            @if(($show_ui ?? false) && $can_sync)
            <div class="space-y-4">
                @if($adJustTypeA > 0)
                @include('jubelio.partials.sync-card', [
                    'title' => 'Sender (Side A)',
                    'whName' => $whAName ?: ($data->sender->name ?? '-'),
                    'jubName' => $JubelioA,
                    'type' => $adJustTypeA,
                    'qty' => $data->total_items,
                    'submittedBy' => $data->submitByA->username ?? null,
                    'referenceId' => $data->a_reference_id,
                    'needsSync' => true,
                    'disabled' => $mappingMissing,
                    'role' => 'sender',
                    'side' => 1,
                    'whType' => $whA,
                    'warning' => $warningA,
                    'transactionId' => $data->id,
                ])
                @endif
                @if($adJustTypeB > 0)
                @include('jubelio.partials.sync-card', [
                    'title' => 'Receiver (Side B)',
                    'whName' => $whBName ?: ($data->receiver->name ?? '-'),
                    'jubName' => $JubelioB,
                    'type' => $adJustTypeB,
                    'qty' => $data->total_items,
                    'submittedBy' => $data->submitByB->username ?? null,
                    'referenceId' => $data->b_reference_id,
                    'needsSync' => true,
                    'disabled' => $mappingMissing,
                    'role' => 'receiver',
                    'side' => 2,
                    'whType' => $whB,
                    'warning' => $warningB,
                    'transactionId' => $data->id,
                ])
                @endif
            </div>
            @endif
        </div>

        <div class="md:col-span-2">
            @if($mappingMissing)
            <div class="mb-6 flex gap-3 rounded-xl border border-red-500/30 bg-red-50 p-6 text-red-600 shadow-sm">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <div>
                    <h3 class="mb-1 font-bold">Mapping Item Hilang</h3>
                    <p class="text-sm opacity-80">Ada {{ $data->item_with_jubelio_count }} item dalam transaksi ini yang belum terhubung ke Jubelio. Anda harus menghubungkannya di menu Item sebelum melakukan sinkron.</p>
                </div>
            </div>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold text-gray-600 uppercase">
                        <tr>
                            <th class="px-6 py-4">Item</th>
                            <th class="px-6 py-4">Code</th>
                            <th class="px-6 py-4 text-center">Qty</th>
                            <th class="px-6 py-4 text-right">Jubelio ID</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($data->details as $detail)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded bg-gray-100">
                                        <svg class="h-5 w-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    </div>
                                    <span class="text-xs font-medium">{{ $detail->item->name ?? '-' }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 font-mono text-[11px] text-gray-500">{{ $detail->item->code ?? '-' }}</td>
                            <td class="px-6 py-4 text-center font-bold">{{ $detail->quantity }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($detail->item && $detail->item->jubelio_item_id)
                                <span class="inline-flex rounded border border-green-500/20 bg-green-50 px-2 py-0.5 font-mono text-xs text-green-600">{{ $detail->item->jubelio_item_id }}</span>
                                @else
                                <span class="inline-flex rounded border border-red-500/20 bg-red-50 px-2 py-0.5 text-xs text-red-600">Missing</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
