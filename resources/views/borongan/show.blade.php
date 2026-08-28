@extends('layouts.app')

@section('title', 'Detail Borongan #' . $borongan->id)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Borongan', 'href' => route('borongan.index')],
    ['title' => 'Detail #' . $borongan->id, 'href' => route('borongan.show', $borongan->id)],
];
$fmt = fn($v) => format_currency($v ?? 0, 'Rp ', 0);
$subTotalItem = $details->sum(fn($d) => (float)$d->total);
$totalQty = $details->sum(fn($d) => (int)$d->quantity);
@endphp

<div class="borongan-show flex w-full flex-col gap-4 p-4 print:gap-2 print:p-0">
    <div class="mb-2 flex items-center justify-between print:hidden">
        <div class="flex items-center gap-4">
            <a href="{{ route('borongan.index') }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <h1 class="text-2xl font-bold tracking-tight">Detail Borongan #{{ $borongan->id }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak
            </button>
            @if($can['edit_borongan'] ?? false)
            <a href="{{ route('borongan.edit', $borongan->id) }}" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Edit</a>
            @endif
        </div>
    </div>

    <div class="hidden print:mb-2 print:block">
        <h1 class="text-base font-bold leading-tight">Detail Borongan #{{ $borongan->id }}</h1>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 print:grid-cols-2 print:gap-2">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm print:rounded-none print:border-gray-300 print:shadow-none">
            <div class="px-6 pt-4 pb-2 print:px-2 print:pt-1 print:pb-0"><h2 class="text-sm font-medium uppercase tracking-wider text-gray-500 print:text-[10px] print:leading-tight">Informasi Borongan</h2></div>
            <div class="grid grid-cols-2 gap-1 px-6 pb-6 text-sm print:gap-0.5 print:px-2 print:pb-2 print:text-xs">
                <span class="text-gray-500">ID Transaksi</span><span class="font-medium">#{{ $borongan->id }}</span>
                <span class="text-gray-500">Tanggal Buat</span><span class="font-medium">{{ $borongan->date ? \Carbon\Carbon::parse($borongan->date)->translatedFormat('d F Y') : '-' }}</span>
                <span class="text-gray-500">Periode</span><span class="font-medium">{{ $borongan->from ? \Carbon\Carbon::parse($borongan->from)->format('d/m/Y') : '-' }} - {{ $borongan->to ? \Carbon\Carbon::parse($borongan->to)->format('d/m/Y') : '-' }}</span>
                <div class="col-span-2 mt-2 grid grid-cols-2 gap-1 border-t pt-2 print:mt-1 print:pt-1">
                    <span class="text-gray-500">Total Qty</span><span class="font-medium">{{ $totalQty }} Pcs</span>
                    <span class="text-gray-500">Tres</span><span class="font-medium">{{ $fmt($borongan->tres) }}</span>
                    <span class="text-gray-500">Permak</span><span class="font-medium">{{ $fmt($borongan->permak) }}</span>
                    <span class="text-gray-500">Lainnya</span><span class="font-medium">{{ $fmt($borongan->lain2) }}</span>
                    <span class="font-bold text-gray-500">Total</span><span class="font-bold text-blue-600">{{ $fmt($borongan->total) }}</span>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm print:rounded-none print:border-gray-300 print:shadow-none">
            <div class="px-6 pt-4 pb-2 print:px-2 print:pt-1 print:pb-0"><h2 class="text-sm font-medium uppercase tracking-wider text-gray-500 print:text-[10px] print:leading-tight">Informasi Pihak</h2></div>
            <div class="grid grid-cols-2 gap-1 px-6 pb-6 text-sm print:gap-0.5 print:px-2 print:pb-2 print:text-xs">
                <span class="text-gray-500">Penjahit</span><span class="text-base font-semibold print:text-xs print:font-medium"><a href="{{ route('produksi.jahit.show', $borongan->jahit_id) }}" class="text-blue-600 hover:underline print:text-black print:no-underline">{{ $borongan->jahit->name ?? '-' }}</a></span>
                <span class="text-gray-500">Dibuat Oleh</span><span class="font-medium">{{ $borongan->user->name ?? '-' }}</span>
            </div>
        </div>
    </div>

    <div class="mt-2 rounded-xl border border-gray-200 bg-white shadow-sm print:mt-0 print:rounded-none print:border-gray-300 print:shadow-none">
        <div class="px-6 py-4 print:px-2 print:py-1"><h2 class="text-lg font-semibold print:text-sm print:font-medium">Rincian Item Gudang</h2></div>
        <div class="w-full overflow-auto print:overflow-visible">
            <table class="w-full text-sm print:text-xs">
                <thead class="bg-gray-100 print:bg-transparent">
                    <tr class="border-b text-xs uppercase text-gray-500 print:text-[10px]">
                        <th class="h-10 px-4 text-left font-medium print:h-auto print:px-2 print:py-1">Kitir</th>
                        <th class="h-10 px-4 text-left font-medium print:h-auto print:px-2 print:py-1">Items</th>
                        <th class="h-10 px-4 text-right font-medium print:h-auto print:px-2 print:py-1">Price</th>
                        <th class="h-10 px-4 text-right font-medium print:h-auto print:px-2 print:py-1">Quantity</th>
                        <th class="h-10 px-4 text-right font-medium print:h-auto print:px-2 print:py-1">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 print:divide-gray-300">
                    @forelse($details as $d)
                    @php $code = $d->item ? ($d->item->name ?? $d->item->id) : ($d->produksi->temp_name ?? '-'); @endphp
                    <tr class="hover:bg-gray-50 print:hover:bg-transparent">
                        <td class="p-3 px-4 font-medium print:p-1 print:px-2 print:leading-tight">{{ $d->produksi->serial ?? '-' }}</td>
                        <td class="p-3 px-4 print:p-1 print:px-2 print:leading-tight">{{ $code }}</td>
                        <td class="p-3 px-4 text-right print:p-1 print:px-2 print:leading-tight">{{ $fmt($d->ongkos) }}</td>
                        <td class="p-3 px-4 text-right print:p-1 print:px-2 print:leading-tight">{{ $d->quantity }}</td>
                        <td class="p-3 px-4 text-right font-semibold print:p-1 print:px-2 print:font-medium print:leading-tight">{{ $fmt($d->total) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="p-4 text-center text-gray-500 print:p-2">Tidak ada item</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="border-t bg-gray-50 print:bg-transparent">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right font-semibold print:px-2 print:py-1 print:font-medium">Subtotal Jumlah:</td>
                        <td class="px-4 py-3 text-right font-bold print:px-2 print:py-1 print:font-medium">{{ $totalQty }} pcs</td>
                        <td class="px-4 py-3 text-right font-bold text-blue-600 print:px-2 print:py-1 print:font-medium print:text-black">{{ $fmt($subTotalItem) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@push('head-css')
<style>
@media print {
    @page {
        margin: 8mm;
        size: A4 portrait;
    }

    body { background: white !important; }
    nav, header, aside { display: none !important; }

    #main-content,
    #app-main-scroll {
        margin-left: 0 !important;
        height: auto !important;
        overflow: visible !important;
    }

    .borongan-show .shadow-sm { box-shadow: none !important; }
    .borongan-show table { border-collapse: collapse; }
    .borongan-show th,
    .borongan-show td {
        padding-top: 2px !important;
        padding-bottom: 2px !important;
        line-height: 1.2 !important;
    }
    .borongan-show a {
        color: inherit !important;
        text-decoration: none !important;
    }
    .borongan-show .text-blue-600 { color: #000 !important; }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
@endpush
@endsection
