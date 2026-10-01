@extends('layouts.app')

@section('title', 'User Activity Audit')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'href' => route('dashboard')],
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'User Activity Audit', 'href' => route('user-activity-audit.index')],
];
$queryBase = request()->except('page');
@endphp

<div class="flex flex-col gap-4 p-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">User Activity Audit</h1>
        <p class="mt-1 text-sm text-gray-500">
            Menyorot transaksi manual yang mencurigakan: entri terlambat / di luar jendela tutup buku,
            user yang sering memindahkan stok ke gudang virtual, dan sell dengan diskon atau total Rp&nbsp;0.
            Jubelio cron (<code class="rounded bg-gray-100 px-1 text-xs">submit_type = 2</code>) disembunyikan secara default.
        </p>
    </div>

    <form method="GET"
          action="{{ route('user-activity-audit.index') }}"
          data-testid="user-activity-audit-filters"
          class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="uaa-from" class="mb-1 block text-sm font-medium text-gray-700">Tanggal transaksi dari</label>
                <input type="date" id="uaa-from" name="from" value="{{ $filters['from'] }}"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="uaa-to" class="mb-1 block text-sm font-medium text-gray-700">s/d</label>
                <input type="date" id="uaa-to" name="to" value="{{ $filters['to'] }}"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="uaa-late" class="mb-1 block text-sm font-medium text-gray-700">Entri terlambat (hari)</label>
                <input type="number" min="1" id="uaa-late" name="late_entry_days" value="{{ $filters['late_entry_days'] }}"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="uaa-min" class="mb-1 block text-sm font-medium text-gray-700">Min. hitungan “sering”</label>
                <input type="number" min="1" id="uaa-min" name="frequent_min_count" value="{{ $filters['frequent_min_count'] }}"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label for="uaa-user" class="mb-1 block text-sm font-medium text-gray-700">User ID (opsional)</label>
                <input type="number" min="1" id="uaa-user" name="user_id" value="{{ $filters['user_id'] ?? '' }}"
                       placeholder="Semua user"
                       class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if($filterUser)
                <p class="mt-1 text-xs text-gray-500">{{ $filterUser->username }} — {{ $filterUser->name }}</p>
                @endif
            </div>
            <div class="flex items-end">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="exclude_jubelio" value="0">
                    <input type="checkbox" name="exclude_jubelio" value="1"
                           @checked($filters['exclude_jubelio'])
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Sembunyikan Jubelio cron
                </label>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="submit"
                    data-testid="user-activity-audit-submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Terapkan filter
            </button>
            <a href="{{ route('user-activity-audit.index') }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Reset
            </a>
        </div>
        <p class="mt-3 text-xs text-gray-500">
            Jendela tutup buku saat ini: tanggal transaksi harus ≥
            <strong>{{ \Illuminate\Support\Carbon::parse($filters['min_allowed_date'])->translatedFormat('d M Y') }}</strong>
            untuk entri baru. CLI: <code class="rounded bg-gray-100 px-1">php artisan app:user-activity-audit</code>
        </p>
    </form>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Sering move → gudang virtual</h2>
            <p class="text-xs text-gray-500">Move selesai ke penerima tipe V.Warehouse, ≥ {{ $filters['frequent_min_count'] }}× dalam periode.</p>
            @if(count($virtualMoves) === 0)
            <p class="mt-4 text-sm italic text-gray-500">Tidak ada user di atas ambang.</p>
            @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">User</th>
                            <th class="py-2 text-right">Move</th>
                            <th class="py-2 pl-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($virtualMoves as $row)
                        <tr>
                            <td class="py-2 pr-2">
                                <span class="font-medium text-gray-900">{{ $row['username'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $row['name'] }}</span>
                            </td>
                            <td class="py-2 text-right tabular-nums font-semibold">{{ number_format($row['move_count']) }}</td>
                            <td class="py-2 pl-2 text-right">
                                <a href="{{ route('user-activity-audit.index', array_merge($queryBase, ['user_id' => $row['user_id']])) }}"
                                   class="text-xs font-medium text-blue-600 hover:underline">Filter</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-900">Sering sell diskon / total nol</h2>
            <p class="text-xs text-gray-500">Sell selesai dengan diskon faktur &gt; 0 atau <code class="rounded bg-gray-100 px-0.5 text-xs">total = 0</code>.</p>
            @if(count($discountedSells) === 0)
            <p class="mt-4 text-sm italic text-gray-500">Tidak ada user di atas ambang.</p>
            @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pr-2">User</th>
                            <th class="py-2 text-right">Sell</th>
                            <th class="py-2 text-right">Rp 0</th>
                            <th class="py-2 text-right">Disc%</th>
                            <th class="py-2 pl-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($discountedSells as $row)
                        <tr>
                            <td class="py-2 pr-2">
                                <span class="font-medium text-gray-900">{{ $row['username'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $row['name'] }}</span>
                            </td>
                            <td class="py-2 text-right tabular-nums font-semibold">{{ number_format($row['sell_count']) }}</td>
                            <td class="py-2 text-right tabular-nums text-amber-800">{{ number_format($row['zero_total_count']) }}</td>
                            <td class="py-2 text-right tabular-nums">{{ number_format($row['discounted_count']) }}</td>
                            <td class="py-2 pl-2 text-right">
                                <a href="{{ route('user-activity-audit.index', array_merge($queryBase, ['user_id' => $row['user_id']])) }}"
                                   class="text-xs font-medium text-blue-600 hover:underline">Filter</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-gray-900">Timing mencurigakan</h2>
        <p class="text-xs text-gray-500">
            Tanggal transaksi sebelum jendela tutup buku <em>atau</em> <code class="rounded bg-gray-100 px-0.5 text-xs">created_at</code>
            ≥ {{ $filters['late_entry_days'] }} hari setelah <code class="rounded bg-gray-100 px-0.5 text-xs">date</code>.
        </p>
        @if($suspicious->isEmpty())
        <p class="mt-4 text-sm italic text-gray-500">Tidak ada transaksi yang cocok.</p>
        @else
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm" data-testid="user-activity-audit-timing-table">
                <thead class="border-b text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-2 py-2">ID</th>
                        <th class="px-2 py-2">Tipe</th>
                        <th class="px-2 py-2">User</th>
                        <th class="px-2 py-2">Tgl transaksi</th>
                        <th class="px-2 py-2">Dibuat</th>
                        <th class="px-2 py-2">Alasan</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($suspicious as $txn)
                    @php $flags = $audit->timingFlags($txn, $filters); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-2 py-2 tabular-nums">{{ $txn->id }}</td>
                        <td class="px-2 py-2">{{ \App\Models\Transaction::typeLabel((int) $txn->type) }}</td>
                        <td class="px-2 py-2">
                            {{ $txn->user?->username ?? $txn->user_id }}
                            @if($txn->user?->name)
                            <span class="block text-xs text-gray-500">{{ $txn->user->name }}</span>
                            @endif
                        </td>
                        <td class="px-2 py-2 whitespace-nowrap">{{ $txn->date?->translatedFormat('d M Y') ?? $txn->date }}</td>
                        <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-600">{{ $txn->created_at?->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-2 py-2 text-xs text-amber-900">{{ implode(' · ', $flags) }}</td>
                        <td class="px-2 py-2 text-right">
                            <a href="{{ route('transactions.show', $txn->id) }}" class="text-xs font-medium text-blue-600 hover:underline">Lihat</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $suspicious->links() }}</div>
        @endif
    </div>
</div>
@endsection
