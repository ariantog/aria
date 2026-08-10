@extends('layouts.app')

@section('title', 'Jubelio Get Orders')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Get Orders', 'href' => route('jubelio.get-orders.index')],
];
$defaultFrom = old('date_from', now()->subDays(3)->toDateString());
$defaultTo = old('date_to', now()->toDateString());
@endphp

<div class="flex flex-col gap-6 p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <h1 class="text-2xl font-bold">Jubelio Get Orders</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-500">
                Temukan order Jubelio yang webhook-nya tidak sampai ke Aria. Order yang belum ada langsung masuk antrian
                <a href="{{ route('jubelio.index') }}" class="text-blue-700 hover:underline">Jubelio Orders</a>.
                Polling otomatis setiap jam untuk {{ $pollDays }} hari terakhir.
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if(! $import)
    <div class="max-w-2xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Sinkronisasi Manual</h2>
            <p class="mt-1 text-xs text-gray-500">Pilih rentang tanggal transaksi di Jubelio. Proses berjalan di background.</p>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('jubelio.get-orders.store') }}" class="space-y-6">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="date_from" class="block text-sm font-medium text-gray-700">Tanggal mulai</label>
                        <input type="date" name="date_from" id="date_from" required value="{{ $defaultFrom }}"
                               class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('date_from')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="space-y-2">
                        <label for="date_to" class="block text-sm font-medium text-gray-700">Tanggal akhir</label>
                        <input type="date" name="date_to" id="date_to" required value="{{ $defaultTo }}"
                               class="h-10 w-full rounded-lg border border-gray-300 px-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('date_to')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" onclick="setPreset(0)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Hari ini</button>
                    <button type="button" onclick="setPreset(3)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">3 hari</button>
                    <button type="button" onclick="setPreset(7)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">7 hari</button>
                    <button type="button" onclick="setPreset(14)"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">14 hari</button>
                </div>

                <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                    <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        Mulai Sinkronisasi
                    </button>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">Sinkronisasi #{{ $import->id }}</h2>
                <p class="text-sm text-gray-500">
                    {{ \Carbon\Carbon::parse($import->from)->translatedFormat('d M Y') }}
                    — {{ \Carbon\Carbon::parse($import->endDateLabel())->translatedFormat('d M Y') }}
                </p>
            </div>
            <div class="text-right text-sm">
                @if($import->isRunning())
                <span class="inline-flex rounded bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Berjalan</span>
                @else
                <span class="inline-flex rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Selesai</span>
                @endif
                <p class="mt-1 font-mono text-xs text-gray-500">
                    Halaman {{ $import->count }}/{{ max($import->total, 1) }}
                    · {{ number_format($import->orders_queued ?? 0) }} order diantri
                </p>
            </div>
        </div>

        @if($import->total > 0)
        <div class="mb-6">
            <div class="mb-1 flex justify-between text-xs text-gray-500">
                <span>Progress</span>
                <span>{{ $import->progressPercent() }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-blue-600 transition-all" style="width: {{ $import->progressPercent() }}%"></div>
            </div>
        </div>
        @endif

        <div class="flex flex-wrap gap-2">
            @if(! $import->isRunning())
            <a href="{{ route('jubelio.index') }}"
               class="inline-flex rounded-lg bg-green-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-800"
               data-testid="jubelio-get-orders-view-queue">
                Lihat Jubelio Orders
            </a>
            @endif
            <form method="POST" action="{{ route('jubelio.get-orders.reset') }}" onsubmit="return confirm('Reset sinkronisasi ini?')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-300 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-100">
                    Reset
                </button>
            </form>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="font-semibold">Order yang baru diantri ({{ $recentlyQueued->count() }})</h2>
            <p class="text-xs text-gray-500">Diproses otomatis oleh cron <code class="text-xs">jubelio:order-jubelio-to-aria</code>.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
                    <tr>
                        <th class="px-6 py-3">Invoice</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Waktu antri</th>
                        <th class="px-6 py-3 text-center">Sync</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($recentlyQueued as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-mono text-xs">
                            <a href="{{ route('jubelio.show', $order) }}" class="text-blue-700 hover:underline">{{ $order->invoice }}</a>
                        </td>
                        <td class="px-6 py-3"><span class="rounded bg-gray-100 px-2 py-0.5 text-xs">{{ $order->order_status }}</span></td>
                        <td class="px-6 py-3 text-gray-500">{{ $order->created_at?->diffForHumans() }}</td>
                        <td class="px-6 py-3 text-center">
                            @if($order->status === 0)
                            <span class="text-blue-600">Pending</span>
                            @elseif($order->status === 1)
                            <span class="text-green-600">OK</span>
                            @else
                            <span class="text-red-600">Error</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-sm italic text-gray-400">
                            @if($import->isRunning())
                            Menunggu sinkronisasi menemukan order yang belum ada di Aria...
                            @else
                            Tidak ada order baru — semua order di rentang ini sudah ada di Aria.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<script>
function setPreset(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - days);
    document.getElementById('date_to').value = to.toISOString().slice(0, 10);
    document.getElementById('date_from').value = from.toISOString().slice(0, 10);
}
</script>
@endsection
