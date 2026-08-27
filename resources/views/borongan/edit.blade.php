@extends('layouts.app')

@section('title', 'Edit Borongan #' . $borongan->id)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Borongan', 'href' => route('borongan.index')],
    ['title' => 'Detail #' . $borongan->id, 'href' => route('borongan.show', $borongan->id)],
    ['title' => 'Edit', 'href' => route('borongan.edit', $borongan->id)],
];
$fmt = fn($v) => format_currency($v ?? 0, 'Rp ', 0);
$subTotalItem = $details->sum(fn($d) => (float)$d->total);
@endphp

<div class="flex w-full flex-col gap-4 p-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('borongan.show', $borongan->id) }}" class="flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Edit Borongan #{{ $borongan->id }}</h1>
                <p class="text-sm text-gray-500">
                    <a href="{{ route('produksi.jahit.show', $borongan->jahit_id) }}" class="font-semibold text-blue-600 hover:underline">{{ $borongan->jahit->name ?? '-' }}</a>
                    &bull; {{ $borongan->from?->format('d/m/Y') }} – {{ $borongan->to?->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('borongan.update', $borongan->id) }}" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @csrf
        @method('PATCH')

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-1">
            <h2 class="text-sm font-medium uppercase tracking-wider text-gray-500">Biaya Tambahan</h2>
            <div class="mt-4 space-y-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium">Permak</label>
                    <input type="number" min="0" step="0.01" name="permak" value="{{ old('permak', $borongan->permak) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Tres</label>
                    <input type="number" min="0" step="0.01" name="tres" value="{{ old('tres', $borongan->tres) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="space-y-1">
                    <label class="text-sm font-medium">Lain-Lain</label>
                    <input type="number" min="0" step="0.01" name="lain2" value="{{ old('lain2', $borongan->lain2) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-6 flex gap-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Simpan</button>
                <a href="{{ route('borongan.show', $borongan->id) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Batal</a>
            </div>
            <p class="mt-4 text-xs text-gray-500">Untuk menambah item baru dari setoran, gunakan halaman Tambah Borongan dengan rentang tanggal yang sama.</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b px-5 py-4">
                <h2 class="text-lg font-semibold">Rincian Item</h2>
                <p class="text-sm text-gray-500">Subtotal item: {{ $fmt($subTotalItem) }}</p>
            </div>
            <div class="max-h-[28rem] overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Kitir</th>
                            <th class="px-4 py-2 text-left font-medium">Item</th>
                            <th class="px-4 py-2 text-right font-medium">Qty</th>
                            <th class="px-4 py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($details as $d)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $d->produksi->serial ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $d->item->item_code ?? $d->produksi->temp_name ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ $d->quantity }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ $fmt($d->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>
</div>
@endsection
