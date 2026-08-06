@extends('layouts.app')

@section('title', 'Setoran Production')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => '#'],
    ['title' => 'Setoran', 'href' => route('produksi.setoran.index')],
];
$hasFilters = collect($filters)->filter(fn($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

<div x-data="{ showFilters: {{ $hasFilters ? 'true' : 'false' }}, updateModal: false, gudangModal: false, sel: { id: null, serial: '' }, openUpdate(id, serial){ this.sel={id,serial}; const h=document.getElementById('update_item_id'); if(h) h.value=''; this.updateModal=true; }, openGudang(id, serial, invoice){ this.sel={id,serial}; const g=document.getElementById('gudang_invoice'); if(g) g.value=invoice||''; this.gudangModal=true; }, get updateAction(){ return this.sel.id ? '{{ url('produksi/setoran') }}/'+this.sel.id+'/edit-item' : '#'; }, get gudangAction(){ return this.sel.id ? '{{ url('produksi/setoran') }}/'+this.sel.id+'/gudang' : '#'; } }" class="p-4">
    {{-- Header --}}
    <div class="mb-6 flex flex-col items-start justify-between gap-4 border-b border-gray-200 pb-4 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Setoran Production</h2>
            <p class="mt-1 text-sm text-gray-500">Manage and filter completed production records.</p>
        </div>
        <button @click="showFilters = !showFilters" class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            <span x-text="showFilters ? 'Hide Filters' : 'Show Filters'"></span>
        </button>
    </div>

    {{-- Filters --}}
    <div x-show="showFilters" x-cloak class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <form method="GET" action="{{ route('produksi.setoran.index') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">From Date</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">To Date</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Potong</label>
                <select name="potong_id" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
                    <option value="">All</option>
                    @foreach($potongList as $w)<option value="{{ $w->id }}" @selected(($filters['potong_id'] ?? '') == $w->id)>{{ $w->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Jahit</label>
                <select name="jahit_id" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
                    <option value="">All</option>
                    @foreach($jahitList as $w)<option value="{{ $w->id }}" @selected(($filters['jahit_id'] ?? '') == $w->id)>{{ $w->name }}</option>@endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Customer</label>
                <input name="customer" value="{{ $filters['customer'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Warna</label>
                <input name="warna" value="{{ $filters['warna'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Kode</label>
                <input name="kode" value="{{ $filters['kode'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Surat Jalan Potong</label>
                <input name="surat_jalan_potong" value="{{ $filters['surat_jalan_potong'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Serial</label>
                <input name="serial" value="{{ $filters['serial'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Invoice</label>
                <input name="invoice" value="{{ $filters['invoice'] ?? '' }}" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-sm font-medium">Status</label>
                <select name="status" class="h-10 rounded-md border border-gray-300 px-3 text-sm">
                    <option value="">All</option>
                    @foreach($statusList as $s)<option value="{{ $s['id'] }}" @selected(($filters['status'] ?? '') == $s['id'])>{{ $s['name'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-span-full mt-2 flex justify-end gap-2">
                <a href="{{ route('produksi.setoran.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Clear</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    Search
                </button>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        @foreach(['Serial','Kode','Potong','SJP','Jumlah','Size','Warna','Costumer','Jahit','QC','Invoice'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-900 whitespace-nowrap">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($produksis as $p)
                    @php
                        $isGudangOrBoth = $p->status === $statusGudang || $p->status === $statusBoth;
                        $rowColor = $p->status === $statusGudang ? 'bg-teal-100 hover:bg-teal-200' : ($p->status === $statusBoth ? 'bg-lime-200 hover:bg-lime-300' : 'hover:bg-gray-50/50');
                        $canEditItem = $can['edit_setoran']
                            && empty($p->invoice)
                            && in_array($p->status, [\App\Models\Produksi::STATUS_PRODUKSI, \App\Models\Produksi::STATUS_SETOR], true);
                    @endphp
                    <tr class="transition-colors {{ $rowColor }}">
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap text-blue-600">
                            <a href="{{ route('produksi.setoran.edit', $p->id) }}" class="hover:underline">{{ $p->serial }}</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($canEditItem)
                                <button @click="openUpdate({{ $p->id }}, '{{ $p->serial }}')" class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200">{{ $p->item->item_code ?? $p->temp_name }}</button>
                            @elseif($isGudangOrBoth || $p->item_id)
                                <div class="text-sm font-bold text-gray-900">{{ $p->item->item_code ?? $p->temp_name }}</div>
                            @else
                                <span class="text-xs italic text-gray-400">{{ $p->temp_name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex flex-col items-center justify-center rounded-md border border-gray-200 bg-gray-50 p-1.5">
                                <span class="mb-0.5 text-[11px] font-medium text-gray-500 whitespace-nowrap">{{ $p->potong_date ? $p->potong_date->translatedFormat('d M Y') : '-' }}</span>
                                @if($p->potong)<span class="w-full max-w-[100px] truncate rounded border border-gray-100 bg-white px-1.5 py-0.5 text-center text-xs font-bold text-gray-900 shadow-sm" title="{{ $p->potong->name }}">{{ $p->potong->name }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap text-gray-900">{{ $p->surat_jalan_potong ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm font-black whitespace-nowrap text-gray-900">{{ $p->quantity }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><span class="w-fit rounded border border-gray-300 bg-white px-2 py-0.5 font-mono text-[10px]">{{ $p->size->name ?? '-' }}</span></td>
                        <td class="px-4 py-3 text-xs font-bold whitespace-nowrap text-gray-600">{{ $p->warna ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap text-gray-900">{{ $p->customer ?: '-' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex flex-col items-center justify-center rounded-md border border-gray-200 bg-gray-50 p-1.5">
                                <span class="mb-0.5 text-[11px] font-medium text-gray-500 whitespace-nowrap">{{ $p->jahit_date ? \Carbon\Carbon::parse($p->jahit_date)->translatedFormat('d M Y') : '-' }}</span>
                                @if($p->jahit)<span class="w-full max-w-[100px] truncate rounded border border-gray-100 bg-white px-1.5 py-0.5 text-center text-xs font-bold text-gray-900 shadow-sm" title="{{ $p->jahit->name }}">{{ $p->jahit->name }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center text-sm whitespace-nowrap">
                            @if($p->qc)<span class="rounded bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{{ $p->qc->name }}</span>@else<span class="font-medium text-gray-400">—</span>@endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($isGudangOrBoth)
                                <span class="text-sm font-bold text-blue-600">{{ $p->invoice }}</span>
                            @elseif($p->item_id)
                                @if($can['gudang_setoran'])
                                    <button @click="openGudang({{ $p->id }}, '{{ $p->serial }}', @js($p->invoice))" class="h-7 rounded bg-gray-800 px-3 text-xs font-medium text-white shadow-sm hover:bg-gray-700">To Gudang</button>
                                @else
                                    <span class="text-xs italic text-gray-400">Ready for Gudang</span>
                                @endif
                            @else
                                <span class="text-xs italic text-gray-400">Belum ada item</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="bg-white px-6 py-12 text-center text-sm text-gray-500">No records found. Adjust your filters to see more results.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $produksis, 'label' => 'records'])
    </div>

    {{-- Update Kode Modal --}}
    <div x-show="updateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="updateModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold">Update Kode Item</h3>
            <p class="mt-1 text-sm text-gray-500">Set true Item for Serial <span class="font-bold text-gray-900" x-text="sel.serial"></span>. Ini akan mengupdate semua kitir dengan id asli yang sama.</p>
            <form :action="updateAction" method="POST" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="space-y-2">
                    <label class="text-sm font-medium">Select Item</label>
                    <div class="relative" x-data="asyncCombobox({ endpoint: '{{ route('items.index') }}', placeholder: 'Search item...', hiddenField: 'update_item_id' })">
                        <input type="text" x-model="query" @input="handleInput()" @focus="handleFocus()" @keydown="handleKeydown($event)" :placeholder="placeholder" autocomplete="off" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <input type="hidden" id="update_item_id" name="item_id">
                        <div x-show="open" x-cloak class="combobox-options" x-ref="optionsList">
                            <template x-for="(item, i) in items" :key="item.id">
                                <div class="combobox-option" :class="{ 'active': i === activeIndex }" @click="selectItem(item)" @mouseenter="activeIndex = i">
                                    <div class="flex flex-col text-xs">
                                        <span class="font-bold">#<span x-text="item.id"></span> - <span x-text="item.name"></span></span>
                                        <span class="text-gray-500" x-text="item.code"></span>
                                    </div>
                                </div>
                            </template>
                            <div x-show="!loading && items.length === 0" class="combobox-option text-gray-400">No results</div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="updateModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Pindah Gudang Modal --}}
    <div x-show="gudangModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="gudangModal = false">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold">Pindah Ke Gudang</h3>
            <p class="mt-1 text-sm text-gray-500">Masukkan nomor Invoice/Transaksi untuk Serial <span class="font-bold text-gray-900" x-text="sel.serial"></span>.</p>
            <form :action="gudangAction" method="POST" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div class="space-y-2">
                    <label class="text-sm font-medium">Invoice Number</label>
                    <input id="gudang_invoice" name="invoice" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="gudangModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">Pindah Gudang</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
