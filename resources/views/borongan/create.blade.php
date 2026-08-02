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
        jahit_id: @js(old('jahit_id', '')),
        ajaxUrl: '{{ route('borongan.ajax') }}'
     })">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold tracking-tight">Tambah Borongan</h1>
        <a href="{{ route('borongan.index') }}" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali
        </a>
    </div>

    <form method="POST" action="{{ route('borongan.store') }}" @submit="if(items.length===0){$event.preventDefault(); alert('Tidak ada item yang bisa disimpan.');}">
        @csrf
        <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-4">
            <div class="space-y-4 md:col-span-1">
                {{-- Filter --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="font-semibold">Pengaturan Filter</h2>
                        <p class="text-sm text-gray-500">Pilih rentang tanggal &amp; Penjahit.</p>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Dari Tanggal *</label>
                            <input type="date" name="from" x-model="from" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('from')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Sampai Tanggal *</label>
                            <input type="date" name="to" x-model="to" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            @error('to')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Penjahit *</label>
                            <select name="jahit_id" x-model="jahit_id" @change="fetchItems()" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                <option value="">Pilih Penjahit...</option>
                                @foreach($jahitList as $j)
                                <option value="{{ $j->id }}">{{ $j->name }}</option>
                                @endforeach
                            </select>
                            @error('jahit_id')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <button type="button" @click="fetchItems()" :disabled="loading" class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium hover:bg-gray-200 disabled:opacity-50">
                            <span x-show="!loading">Cari Baris Item</span>
                            <span x-show="loading">Memuat Data...</span>
                        </button>
                    </div>
                </div>

                {{-- Biaya Tambahan --}}
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4">
                        <h2 class="font-semibold">Biaya Tambahan</h2>
                        <p class="text-sm text-gray-500">Potongan/Tambahan Ongkos.</p>
                    </div>
                    <div class="space-y-4 p-6">
                        <div class="space-y-2"><label class="text-sm font-medium">Permak</label><input type="number" min="0" name="permak" x-model.number="permak" value="{{ old('permak', 0) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></div>
                        <div class="space-y-2"><label class="text-sm font-medium">Tres</label><input type="number" min="0" name="tres" x-model.number="tres" value="{{ old('tres', 0) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></div>
                        <div class="space-y-2"><label class="text-sm font-medium">Lain-Lain</label><input type="number" min="0" name="lain2" x-model.number="lain2" value="{{ old('lain2', 0) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></div>
                    </div>
                    <div class="flex flex-col gap-2 border-t bg-gray-50/50 px-6 py-4">
                        <div class="flex w-full justify-between text-sm font-medium"><span>Subtotal Item:</span><span x-text="fmt(subTotalItem)"></span></div>
                        <div class="mt-1 flex w-full justify-between border-t pt-2 text-lg font-bold"><span>Grand Total:</span><span class="text-blue-600" x-text="fmt(grandTotal)"></span></div>
                        <button type="submit" :disabled="items.length===0" class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">Simpan Pembayaran</button>
                    </div>
                </div>
            </div>

            {{-- Rincian Item --}}
            <div class="md:col-span-3">
                <div class="h-full rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="text-base font-semibold">Tabel Rincian Item (Status Gudang)</h2>
                        <div class="text-sm font-medium text-gray-500"><span x-text="totalQty"></span> baris ditemukan</div>
                    </div>
                    <div class="max-h-[600px] w-full overflow-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 z-10 bg-white/95 shadow-sm backdrop-blur">
                                <tr class="border-b text-left text-gray-500">
                                    <th class="h-10 w-12 px-4 font-medium">No</th>
                                    <th class="h-10 px-4 font-medium">Serial Prod</th>
                                    <th class="h-10 px-4 font-medium">Kode Item / Temp</th>
                                    <th class="h-10 w-20 px-4 text-right font-medium">Qty</th>
                                    <th class="h-10 w-32 px-4 text-right font-medium">Ongkos Satuan</th>
                                    <th class="h-10 w-32 px-4 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="item.produksi_id">
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3" x-text="idx + 1"></td>
                                        <td class="p-3"><a :href="item.edit_link" target="_blank" class="font-mono text-blue-600 hover:underline" x-text="item.serial"></a></td>
                                        <td class="p-3 font-medium"><span x-text="item.code"></span><span x-show="item.item" class="ml-2 text-xs text-gray-500" x-text="item.item ? '('+item.item.name+')' : ''"></span></td>
                                        <td class="p-3 text-right" x-text="item.quantity"></td>
                                        <td class="p-3 text-right" x-text="fmt(item.ongkos)"></td>
                                        <td class="p-3 text-right font-semibold" x-text="fmt(item.total)"></td>
                                    </tr>
                                </template>
                                <tr x-show="loading"><td colspan="6" class="p-8 text-center text-gray-400">Memuat...</td></tr>
                                <tr x-show="!loading && items.length === 0"><td colspan="6" class="p-8 text-center text-gray-500">Pilih Filter Tanggal dan Penjahit untuk melihat daftar item produksi (Gudang).</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function boronganCreate(cfg) {
    return {
        from: cfg.from, to: cfg.to, jahit_id: cfg.jahit_id, ajaxUrl: cfg.ajaxUrl,
        permak: {{ (int)old('permak', 0) }}, tres: {{ (int)old('tres', 0) }}, lain2: {{ (int)old('lain2', 0) }},
        items: [], loading: false,
        get subTotalItem() { return this.items.reduce((a, c) => a + (parseFloat(c.total) || 0), 0); },
        get grandTotal() { return this.subTotalItem + (Number(this.permak)||0) + (Number(this.tres)||0) + (Number(this.lain2)||0); },
        get totalQty() { return this.items.reduce((a, c) => a + (parseInt(c.quantity) || 0), 0); },
        fmt(v) { return 'Rp ' + Number(v || 0).toLocaleString('id-ID'); },
        init() { if (this.from && this.to && this.jahit_id) this.fetchItems(); },
        async fetchItems() {
            if (!this.from || !this.to || !this.jahit_id) return;
            this.loading = true; this.items = [];
            try {
                const url = new URL(this.ajaxUrl, window.location.origin);
                url.searchParams.set('from', this.from);
                url.searchParams.set('to', this.to);
                url.searchParams.set('jahit_id', this.jahit_id);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.items = Array.isArray(data) ? data : [];
            } catch (e) {
                console.error(e); this.items = [];
            } finally { this.loading = false; }
        },
    };
}
</script>
@endpush
@endsection
