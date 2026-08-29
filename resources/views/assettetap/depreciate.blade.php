@extends('layouts.app')

@section('title', 'Run Monthly Depreciation')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Asset Tetap', 'href' => route('assettetap.index')],
    ['title' => 'Penyusutan', 'href' => route('assettetap.depreciate')],
];
$fmt = fn ($v) => 'Rp '.format_amount($v, 0);
$previewTotal = collect($preview)->sum('amount');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Run Monthly Depreciation</h2>
        <p class="mt-0.5 text-sm text-gray-500">Membuat satu transaksi type 18 untuk asset yang masih punya nilai buku di atas residu. Tidak mengubah transaksi lama.</p>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
    @endif

    <form method="GET" action="{{ route('assettetap.depreciate') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <label class="text-sm font-medium" for="month">Bulan</label>
                <select id="month" name="month" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($month === $m)>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid gap-1.5">
                <label class="text-sm font-medium" for="year">Tahun</label>
                <select id="year" name="year" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                    @foreach($yearList as $y)
                        <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Preview</button>
        </div>
    </form>

    <form method="POST" action="{{ route('assettetap.depreciate.store') }}" class="space-y-4" data-testid="assettetap-depreciate-form">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        <input type="hidden" name="year" value="{{ $year }}">

        <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
                <label for="expense_account_id" class="text-sm font-medium text-gray-700">Akun beban penyusutan</label>
                <select id="expense_account_id" name="expense_account_id" required data-testid="assettetap-expense-account"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Pilih akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('expense_account_id', $expenseAccountId) === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1.5">
                <label for="contra_account_id" class="text-sm font-medium text-gray-700">Akun akumulasi penyusutan</label>
                <select id="contra_account_id" name="contra_account_id" required data-testid="assettetap-contra-account"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Pilih akun</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('contra_account_id', $contraAccountId) === (string) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm" data-testid="assettetap-depreciate-preview">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Asset</th>
                        <th class="px-3 py-2 text-right">Sisa disusutkan</th>
                        <th class="px-3 py-2 text-right">Jumlah bulan ini</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($preview as $line)
                        <tr class="border-t border-gray-100">
                            <td class="px-3 py-2">{{ $line['item']->name }} <span class="font-mono text-xs text-gray-400">{{ $line['item']->code }}</span></td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($line['remaining']) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ $fmt($line['amount']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Tidak ada asset yang perlu disusutkan untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
                @if(count($preview))
                <tfoot>
                    <tr class="border-t bg-gray-50 font-semibold">
                        <td class="px-3 py-2">Total</td>
                        <td></td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $fmt($previewTotal) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        <button type="submit" data-testid="assettetap-depreciate-submit"
                @disabled(count($preview) === 0)
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
            Post depreciation
        </button>
    </form>
</div>
@endsection
