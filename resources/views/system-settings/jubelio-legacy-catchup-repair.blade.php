@extends('layouts.app')

@section('title', 'Jubelio Legacy Catch-up Repair')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Jubelio Catch-up Repair', 'href' => route('jubelio-legacy-catchup-repair.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4"
     x-data="{
        selected: @js(collect(old('transaction_ids', []))->map(fn ($id) => (int) $id)->values()->all()),
        toggle(id) {
            id = Number(id);
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter((x) => x !== id);
            } else {
                this.selected.push(id);
            }
        },
        selectAllVisible() {
            const ids = @js($sells->getCollection()->pluck('id')->values()->all());
            this.selected = [...new Set([...this.selected, ...ids])];
        },
        clearSelection() { this.selected = []; },
        canSubmit() { return this.selected.length > 0; }
     }">
    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Jubelio legacy catch-up repair</h1>
            <p class="mt-1 max-w-3xl text-sm text-gray-600">
                One-off tool: lists Jubelio cron sells that posted with an old <strong>transaction date</strong> after the bad get-orders deploy.
                Restore stock by posting a <strong>move</strong> from a virtual warehouse into the sell&rsquo;s sender warehouse.
                <span class="text-amber-700">Temporary — remove this page after production cleanup.</span>
            </p>
            <p class="mt-2 font-mono text-xs text-gray-500">
                Filter: <code>date &lt; {{ $criteria['date_before'] }}</code>,
                <code>created_at &gt; {{ $criteria['created_after'] }}</code>,
                <code>user_id = {{ app(\App\Services\Jubelio\JubelioLegacyCatchupStockRestoreService::class)->cronUserId() }}</code>,
                Jubelio submit.
            </p>
        </div>
        <a href="{{ route('system-settings.index') }}" class="text-sm font-medium text-blue-600 hover:underline">← System Settings</a>
    </div>

    @if($flash['success'] ?? null)
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? null)
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    @if(!empty($flash['restore_report']))
    <details class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-700">
        <summary class="cursor-pointer font-medium">Detail hasil terakhir</summary>
        <pre class="mt-2 overflow-x-auto text-xs">{{ json_encode($flash['restore_report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </details>
    @endif

    <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div>
            <label for="warehouse_id" class="mb-1 block text-xs font-medium text-gray-500">Filter gudang (sell sender)</label>
            <select id="warehouse_id" name="warehouse_id" class="h-9 min-w-[14rem] rounded-md border border-gray-300 px-3 text-sm">
                <option value="">Semua gudang</option>
                @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" @selected($warehouseFilter === $wh->id)>{{ $wh->name }} (#{{ $wh->id }})</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="h-9 rounded-md bg-gray-800 px-4 text-sm font-medium text-white hover:bg-gray-900">Terapkan filter</button>
    </form>

    <form method="POST" action="{{ route('jubelio-legacy-catchup-repair.restore') }}" class="flex flex-col gap-4">
        @csrf
        @if($warehouseFilter)
        <input type="hidden" name="warehouse_id" value="{{ $warehouseFilter }}">
        @endif

        <div class="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div>
                <label for="virtual_warehouse_id" class="mb-1 block text-xs font-medium text-gray-500">Virtual warehouse (pengirim move)</label>
                <select id="virtual_warehouse_id"
                        name="virtual_warehouse_id"
                        data-testid="jubelio-catchup-vwh"
                        required
                        class="h-9 min-w-[16rem] rounded-md border border-gray-300 px-3 text-sm">
                    <option value="">— pilih V. warehouse —</option>
                    @foreach($virtualWarehouses as $vwh)
                    <option value="{{ $vwh->id }}" @selected((int) old('virtual_warehouse_id') === $vwh->id)>{{ $vwh->name }} (#{{ $vwh->id }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="selectAllVisible()" class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Pilih semua di halaman</button>
                <button type="button" @click="clearSelection()" class="h-9 rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Kosongkan pilihan</button>
            </div>
            <p class="text-sm text-gray-600" x-text="selected.length + ' transaksi dipilih'"></p>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 w-10"></th>
                            <th class="px-4 py-3">ID / Invoice</th>
                            <th class="px-4 py-3">Tgl transaksi</th>
                            <th class="px-4 py-3">Dibuat</th>
                            <th class="px-4 py-3">Gudang</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Restore</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($sells as $sell)
                        @php $restoredMoveId = $restoredMoveIds[$sell->id] ?? null; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 align-top">
                                @if(!$restoredMoveId)
                                <input type="checkbox"
                                       name="transaction_ids[]"
                                       value="{{ $sell->id }}"
                                       data-testid="jubelio-catchup-row-{{ $sell->id }}"
                                       class="rounded border-gray-300"
                                       :checked="selected.includes({{ $sell->id }})"
                                       @change="toggle({{ $sell->id }})">
                                @endif
                            </td>
                            <td class="px-4 py-3 align-top">
                                <a href="{{ route('transactions.show', $sell) }}" class="font-medium text-blue-600 hover:underline">#{{ $sell->id }}</a>
                                <div class="font-mono text-xs text-gray-600">{{ $sell->invoice }}</div>
                            </td>
                            <td class="px-4 py-3 align-top whitespace-nowrap">{{ $sell->date?->toDateString() }}</td>
                            <td class="px-4 py-3 align-top whitespace-nowrap text-xs text-gray-600">{{ $sell->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3 align-top">{{ $sell->sender?->name ?? '—' }}</td>
                            <td class="px-4 py-3 align-top">{{ format_amount($sell->total_items, 0) }}</td>
                            <td class="px-4 py-3 align-top text-xs">
                                @if($restoredMoveId)
                                <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800">Sudah restore</span>
                                <a href="{{ route('transactions.show', $restoredMoveId) }}" class="text-blue-600 hover:underline">Move #{{ $restoredMoveId }}</a>
                                @else
                                <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">Tidak ada transaksi yang cocok dengan filter.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sells->hasPages())
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $sells->links() }}
            </div>
            @endif
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <label class="flex items-start gap-2">
                <input type="checkbox" name="confirm" value="1" required class="mt-1 rounded border-amber-400" @checked(old('confirm'))>
                <span>Saya mengerti: ini membuat transaksi <strong>move</strong> nyata (stok gudang fisik naik). Piutang / saldo customer dari sell salah <strong>tidak</strong> dibalik otomatis.</span>
            </label>
        </div>

        <button type="submit"
                data-testid="jubelio-catchup-restore-submit"
                :disabled="!canSubmit()"
                :class="canSubmit() ? 'bg-blue-600 hover:bg-blue-700' : 'cursor-not-allowed bg-gray-300'"
                class="inline-flex h-10 items-center justify-center rounded-lg px-6 text-sm font-semibold text-white">
            Kembalikan stok (buat move)
        </button>
    </form>
</div>
@endsection
