@extends('layouts.app')

@section('title', 'Tambah Borongan')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Borongan', 'href' => route('borongan.index')],
    ['title' => 'Tambah Borongan', 'href' => route('borongan.create')],
];
@endphp

<div class="flex flex-col gap-4 p-4"
     x-data="boronganCreate({
        from: @js(old('from', $defaultFrom)),
        to: @js(old('to', $defaultTo)),
        ajaxUrl: '{{ route('borongan.ajax') }}'
     })">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Tambah Borongan</h1>
            <p class="mt-1 text-sm text-gray-500">Generate weekly payment for all penjahit with gudang items in the selected date range.</p>
        </div>
        <a href="{{ route('borongan.index') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </div>

    <form method="POST" action="{{ route('borongan.store') }}" @submit="if(groups.length===0){$event.preventDefault(); alert('Tidak ada item yang bisa disimpan.');}">
        @csrf
        <input type="hidden" name="from" :value="from">
        <input type="hidden" name="to" :value="to">
        <template x-for="(group, gi) in groups" :key="group.jahit_id">
            <input type="hidden" :name="`batches[${gi}][jahit_id]`" :value="group.jahit_id">
        </template>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Rentang Tanggal</h2>
            <p class="text-sm text-gray-500">Hanya item setoran dengan status <span class="font-medium">Gudang</span> (belum dibayar) yang akan dipilih.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Dari Tanggal *</label>
                    <input type="date" x-model="from" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @error('from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Sampai Tanggal *</label>
                    <input type="date" x-model="to" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @error('to')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end">
                    <button type="button" @click="fetchGroups()" :disabled="loading" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">
                        <span x-show="!loading">Cari Semua Penjahit</span>
                        <span x-show="loading">Memuat Data...</span>
                    </button>
                </div>
            </div>
        </div>

        <template x-if="groups.length > 0">
            <div class="mt-4 space-y-4">
                <template x-for="(group, gi) in groups" :key="group.jahit_id">
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-base font-semibold">
                                    <a :href="group.jahit_link" class="text-blue-600 hover:underline" x-text="group.jahit_name"></a>
                                </h2>
                                <p class="text-sm text-gray-500">
                                    <span x-text="group.total_qty"></span> pcs &bull; Subtotal <span x-text="fmt(group.subtotal)"></span>
                                    <template x-if="group.is_append">
                                        <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Tambah ke borongan #<span x-text="group.borongan_id"></span></span>
                                    </template>
                                </p>
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-medium uppercase text-gray-500">Permak</label>
                                    <input type="number" min="0" step="0.01" :name="`batches[${gi}][permak]`" x-model.number="group.permak" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium uppercase text-gray-500">Tres</label>
                                    <input type="number" min="0" step="0.01" :name="`batches[${gi}][tres]`" x-model.number="group.tres" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-medium uppercase text-gray-500">Lain-Lain</label>
                                    <input type="number" min="0" step="0.01" :name="`batches[${gi}][lain2]`" x-model.number="group.lain2" class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                </div>
                            </div>
                        </div>
                        <div class="max-h-80 overflow-auto">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white/95 text-left text-gray-500">
                                    <tr class="border-b">
                                        <th class="h-10 px-4 font-medium">No</th>
                                        <th class="h-10 px-4 font-medium">Serial</th>
                                        <th class="h-10 px-4 font-medium">Kode Item</th>
                                        <th class="h-10 px-4 text-right font-medium">Qty</th>
                                        <th class="h-10 px-4 text-right font-medium">Ongkos</th>
                                        <th class="h-10 px-4 text-right font-medium">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, idx) in group.items" :key="item.produksi_id">
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="p-3" x-text="idx + 1"></td>
                                            <td class="p-3"><a :href="item.edit_link" target="_blank" class="font-mono text-blue-600 hover:underline" x-text="item.serial"></a></td>
                                            <td class="p-3 font-medium" x-text="item.code"></td>
                                            <td class="p-3 text-right" x-text="item.quantity"></td>
                                            <td class="p-3 text-right" x-text="fmt(item.ongkos)"></td>
                                            <td class="p-3 text-right font-semibold" x-text="fmt(item.total)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex justify-end border-t bg-gray-50 px-6 py-3 text-sm font-bold">
                            Grand Total: <span class="ml-2 text-blue-600" x-text="fmt(groupGrandTotal(group))"></span>
                        </div>
                    </div>
                </template>

                <div class="flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50 px-6 py-4">
                    <div>
                        <p class="text-sm text-gray-600"><span x-text="groups.length"></span> penjahit &bull; <span x-text="totalQtyAll"></span> pcs total</p>
                        <p class="text-lg font-bold text-blue-700">Total Semua: <span x-text="fmt(grandTotalAll)"></span></p>
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        Simpan Semua Borongan
                    </button>
                </div>
            </div>
        </template>

        <div x-show="!loading && searched && groups.length === 0" class="mt-4 rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-gray-500">
            Tidak ada item gudang (belum dibayar) pada rentang tanggal ini.
        </div>
    </form>
</div>

@push('scripts')
<script>
function boronganCreate(cfg) {
    return {
        from: cfg.from,
        to: cfg.to,
        ajaxUrl: cfg.ajaxUrl,
        groups: [],
        loading: false,
        searched: false,
        fmt(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); },
        groupGrandTotal(group) {
            const fees = (Number(group.permak) || 0) + (Number(group.tres) || 0) + (Number(group.lain2) || 0);
            return (Number(group.subtotal) || 0) + fees;
        },
        get grandTotalAll() {
            return this.groups.reduce((sum, g) => sum + this.groupGrandTotal(g), 0);
        },
        get totalQtyAll() {
            return this.groups.reduce((sum, g) => sum + (Number(g.total_qty) || 0), 0);
        },
        async fetchGroups() {
            if (!this.from || !this.to) return;
            this.loading = true;
            this.searched = true;
            this.groups = [];
            try {
                const url = new URL(this.ajaxUrl, window.location.origin);
                url.searchParams.set('from', this.from);
                url.searchParams.set('to', this.to);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const rows = Array.isArray(data) ? data : [];
                this.groups = rows.map((g) => ({
                    ...g,
                    permak: g.is_append ? Number(g.existing_permak) || 0 : 0,
                    tres: g.is_append ? Number(g.existing_tres) || 0 : 0,
                    lain2: g.is_append ? Number(g.existing_lain2) || 0 : 0,
                }));
            } catch (e) {
                console.error(e);
                this.groups = [];
            } finally {
                this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
