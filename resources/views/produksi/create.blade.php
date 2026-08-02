@extends('layouts.app')

@section('title', 'New Potong Entry')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => route('produksi.index')],
    ['title' => 'New Entry', 'href' => route('produksi.create')],
];
@endphp

<div class="p-4" x-data="produksiCreate()">
    <div class="mb-8 flex items-center gap-4">
        <a href="{{ route('produksi.index') }}" class="flex h-9 w-9 items-center justify-center rounded-full hover:bg-gray-100">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900">New Production Entry</h2>
            <p class="text-sm text-zinc-500">Record a new batch of production at the Potong stage.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('produksi.store') }}" class="space-y-8">
        @csrf
        {{-- General info --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
            <h3 class="mb-6 flex items-center gap-2 text-lg font-semibold">
                <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                General Information
            </h3>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Date</label>
                    <input type="date" name="date" value="{{ old('date', now()->format('Y-m-d')) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @error('date')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Worker (Potong)</label>
                    <select name="potong_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Select worker</option>
                        @foreach($workers as $w)
                        <option value="{{ $w->id }}" @selected(old('potong_id') == $w->id)>{{ $w->name }}</option>
                        @endforeach
                    </select>
                    @error('potong_id')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Surat Jalan Potong</label>
                    <input name="surat_jalan_potong" value="{{ old('surat_jalan_potong') }}" placeholder="Optional" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    @error('surat_jalan_potong')<p class="text-sm text-red-500">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Items table --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-zinc-200 p-6">
                <h3 class="flex items-center gap-2 text-lg font-semibold">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Production Items
                </h3>
                <button type="button" @click="addItem()" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Row
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200">
                    <thead class="bg-zinc-50/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">Product Name</th>
                            <th class="w-32 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">Size</th>
                            <th class="w-24 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">Qty</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">Warna</th>
                            <th class="w-16 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white">
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="hover:bg-zinc-50/30">
                                <td class="px-4 py-3"><input :name="`items[${index}][name]`" x-model="item.name" placeholder="Item/Model name" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></td>
                                <td class="px-4 py-3">
                                    <select :name="`items[${index}][size_id]`" x-model="item.size_id" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm">
                                        <option value="">Size</option>
                                        @foreach($sizes as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3"><input type="number" min="1" :name="`items[${index}][qty]`" x-model="item.qty" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></td>
                                <td class="px-4 py-3"><input :name="`items[${index}][customer]`" x-model="item.customer" placeholder="Customer" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></td>
                                <td class="px-4 py-3"><input :name="`items[${index}][warna]`" x-model="item.warna" placeholder="Color" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" x-show="items.length > 1" @click="removeItem(index)" class="flex h-8 w-8 items-center justify-center rounded text-zinc-400 hover:text-red-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="{{ route('produksi.index') }}" class="rounded-md px-4 py-2 text-sm text-zinc-500 hover:bg-gray-100">Cancel</a>
            <button type="submit" class="inline-flex min-w-[150px] items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Save Production
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function produksiCreate() {
    return {
        items: [{ name: '', size_id: '', qty: 1, customer: '', warna: '' }],
        addItem() { this.items.push({ name: '', size_id: '', qty: 1, customer: '', warna: '' }); },
        removeItem(i) { this.items.splice(i, 1); },
    };
}
</script>
@endpush
@endsection
