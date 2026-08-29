@extends('layouts.app')

@section('title', $item->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Asset Tetap', 'href' => route('assettetap.index')],
    ['title' => $item->name, 'href' => route('assettetap.show', $item)],
];
$fmt = fn ($v) => 'Rp '.format_amount($v, 0);
$bought = $register && $register->hasBuyTransaction();
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $item->name }}</h2>
            <p class="mt-0.5 font-mono text-sm text-gray-500">{{ $item->code }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($can['create'] && ! $bought)
            <a href="{{ route('assettetap.buy', $item) }}" data-testid="assettetap-record-buy"
               class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Record Buy</a>
            @endif
            @if($can['edit'])
            <a href="{{ route('assettetap.edit', $item) }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endif
            @if($can['delete'])
            <form method="POST" action="{{ route('assettetap.destroy', $item) }}" onsubmit="return confirm('Hapus asset dari register?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-3" data-testid="assettetap-nbv-cards">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">Harga perolehan</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $bought ? $fmt($register->buy_price) : '—' }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">Akumulasi penyusutan</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $fmt($accumulated) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">Nilai buku</p>
            <p class="mt-1 text-lg font-semibold tabular-nums" data-testid="assettetap-nbv">{{ $bought ? $fmt($nbv) : '—' }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-gray-900">Register</h3>
        <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Tanggal beli</dt><dd class="font-medium">{{ $bought ? $register->buy_date?->format('d/m/Y') : 'Belum dicatat' }}</dd></div>
            <div><dt class="text-gray-500">Umur manfaat</dt><dd class="font-medium">{{ $register->useful_life_months ?? '—' }} bulan</dd></div>
            <div><dt class="text-gray-500">Penyusutan / bulan</dt><dd class="font-medium">{{ $bought ? $fmt($monthly) : '—' }}</dd></div>
            <div><dt class="text-gray-500">Nilai residu</dt><dd class="font-medium">{{ $fmt($register->residual_value ?? 0) }}</dd></div>
            <div><dt class="text-gray-500">Expire</dt><dd class="font-medium">{{ $bought ? $register->expire_date?->format('d/m/Y') : '—' }}</dd></div>
            <div><dt class="text-gray-500">Gudang</dt><dd class="font-medium">{{ $register?->warehouse?->name ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-gray-500">Deskripsi</dt><dd>{{ $item->description ?: '—' }}</dd></div>
        </dl>
        @if($bought && $register->buyTransaction)
        <p class="mt-4 text-sm">
            Transaksi beli:
            <a href="{{ route('transactions.show', $register->buyTransaction) }}" class="font-medium text-blue-700 hover:underline">#{{ $register->buyTransaction->invoice }}</a>
        </p>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold">Penyusutan (type 18)</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-2 text-left">Tanggal</th>
                    <th class="px-3 py-2 text-left">Invoice</th>
                    <th class="px-3 py-2 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse($depreciationLines as $line)
                    <tr class="border-t border-gray-100">
                        <td class="px-3 py-2">{{ $line->date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            @if($line->transaction)
                                <a href="{{ route('transactions.show', $line->transaction) }}" class="text-blue-700 hover:underline">{{ $line->transaction->invoice }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($line->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Belum ada penyusutan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
