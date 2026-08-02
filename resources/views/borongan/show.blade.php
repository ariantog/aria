@extends('layouts.app')

@section('title', 'Detail Borongan #' . $borongan->id)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Borongan', 'href' => route('borongan.index')],
    ['title' => 'Detail #' . $borongan->id, 'href' => route('borongan.show', $borongan->id)],
];
$fmt = fn($v) => 'Rp ' . number_format((float)($v ?? 0), 0, ',', '.');
$subTotalItem = $details->sum(fn($d) => (float)$d->total);
$totalQty = $details->sum(fn($d) => (int)$d->quantity);
@endphp

<div class="flex w-full flex-col gap-4 p-4">
    <div class="hover-none-print mb-2 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('borongan.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="text-2xl font-bold tracking-tight">Detail Borongan #{{ $borongan->id }}</h1>
        </div>
        <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-200">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Cetak
        </button>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="px-6 pt-4 pb-2"><h2 class="text-sm font-medium uppercase tracking-wider text-gray-500">Informasi Borongan</h2></div>
            <div class="grid grid-cols-2 gap-1 px-6 pb-6 text-sm">
                <span class="text-gray-500">ID Transaksi</span><span class="font-medium">#{{ $borongan->id }}</span>
                <span class="text-gray-500">Tanggal Buat</span><span class="font-medium">{{ $borongan->date ? \Carbon\Carbon::parse($borongan->date)->translatedFormat('d F Y') : '-' }}</span>
                <span class="text-gray-500">Periode</span><span class="font-medium">{{ $borongan->from ? \Carbon\Carbon::parse($borongan->from)->format('d/m/Y') : '-' }} - {{ $borongan->to ? \Carbon\Carbon::parse($borongan->to)->format('d/m/Y') : '-' }}</span>
                <div class="col-span-2 mt-2 grid grid-cols-2 gap-1 border-t pt-2">
                    <span class="text-gray-500">Total Qty</span><span class="font-medium">{{ $totalQty }} Pcs</span>
                    <span class="text-gray-500">Tres</span><span class="font-medium">{{ $fmt($borongan->tres) }}</span>
                    <span class="text-gray-500">Permak</span><span class="font-medium">{{ $fmt($borongan->permak) }}</span>
                    <span class="text-gray-500">Lainnya</span><span class="font-medium">{{ $fmt($borongan->lain2) }}</span>
                    <span class="font-bold text-gray-500">Total</span><span class="font-bold text-blue-600">{{ $fmt($borongan->total) }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="px-6 pt-4 pb-2"><h2 class="text-sm font-medium uppercase tracking-wider text-gray-500">Informasi Pihak</h2></div>
            <div class="grid grid-cols-2 gap-1 px-6 pb-6 text-sm">
                <span class="text-gray-500">Penjahit</span><span class="text-base font-semibold text-blue-600">{{ $borongan->jahit->name ?? '-' }}</span>
                <span class="text-gray-500">Dibuat Oleh</span><span class="font-medium">{{ $borongan->user->name ?? '-' }}</span>
            </div>
        </div>
    </div>

    <div class="mt-2 rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="px-6 py-4"><h2 class="text-lg font-semibold">Rincian Item Gudang</h2></div>
        <div class="w-full overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100">
                    <tr class="border-b text-xs uppercase text-gray-500">
                        <th class="h-10 px-4 text-left font-medium">Kitir</th>
                        <th class="h-10 px-4 text-left font-medium">Items</th>
                        <th class="h-10 px-4 text-right font-medium">Price</th>
                        <th class="h-10 px-4 text-right font-medium">Quantity</th>
                        <th class="h-10 px-4 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($details as $d)
                    @php $code = $d->item ? ($d->item->name ?? $d->item->id) : ($d->produksi->temp_name ?? '-'); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 px-4 font-medium">{{ $d->produksi->serial ?? '-' }}</td>
                        <td class="p-3 px-4">{{ $code }}</td>
                        <td class="p-3 px-4 text-right">{{ $fmt($d->ongkos) }}</td>
                        <td class="p-3 px-4 text-right">{{ $d->quantity }}</td>
                        <td class="p-3 px-4 text-right font-semibold">{{ $fmt($d->total) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500">Tidak ada item</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right font-semibold">Subtotal Jumlah:</td>
                        <td class="px-4 py-3 text-right font-bold">{{ $totalQty }} pcs</td>
                        <td class="px-4 py-3 text-right font-bold text-blue-600">{{ $fmt($subTotalItem) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@push('head-css')
<style>
@media print {
    .hover-none-print { display: none !important; }
    body { background: white !important; }
    nav, header, aside { display: none !important; }
    .shadow-sm { box-shadow: none !important; }
}
</style>
@endpush
@endsection
