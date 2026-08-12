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
                        @foreach(['Serial','Parent','Kode','Potong','SJP','Jumlah','Size','Warna','Costumer','Jahit','QC','Pritil','Invoice'] as $h)
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
                            && $p->status === \App\Models\Produksi::STATUS_SETOR
                            && empty($p->invoice);
                    @endphp
                    <tr class="transition-colors {{ $rowColor }}">
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap text-blue-600">
                            <a href="{{ route('produksi.setoran.edit', $p->id) }}" class="hover:underline">{{ $p->serial }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @if($p->original_id)
                            @include('partials.filter-link', ['route' => 'produksi.setoran.index', 'param' => 'serial', 'value' => $p->parentSerial(), 'filters' => $filters])
                            @else
                            <span class="font-medium text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($p->item_id)
                            <div class="flex items-center gap-1.5">
                                <a href="{{ route('items.show', $p->item_id) }}" class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200">{{ $p->item->item_code ?? $p->temp_name }}</a>
                                @if($canEditItem)
                                <button @click="openUpdate({{ $p->id }}, '{{ $p->serial }}')" title="Change item" class="flex h-6 w-6 items-center justify-center rounded border border-gray-200 text-gray-500 hover:bg-gray-50">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @endif
                            </div>
                            @elseif($canEditItem)
                                <button @click="openUpdate({{ $p->id }}, '{{ $p->serial }}')" class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200">{{ $p->temp_name }}</button>
                            @else
                                <span class="text-xs italic text-gray-400">{{ $p->temp_name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @include('produksi.partials.worker-cell', ['worker' => $p->potong, 'type' => 'potong', 'date' => $p->potong_date])
                        </td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @include('partials.filter-link', ['route' => 'produksi.setoran.index', 'param' => 'surat_jalan_potong', 'value' => $p->surat_jalan_potong, 'filters' => $filters, 'class' => 'text-gray-900 hover:text-blue-600 hover:underline'])
                        </td>
                        <td class="px-4 py-3 text-sm font-black whitespace-nowrap text-gray-900">{{ $p->quantity }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><span class="w-fit rounded border border-gray-300 bg-white px-2 py-0.5 font-mono text-[10px]">{{ $p->size->name ?? '-' }}</span></td>
                        <td class="px-4 py-3 text-xs font-bold whitespace-nowrap text-gray-600">{{ $p->warna ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @include('partials.filter-link', ['route' => 'produksi.setoran.index', 'param' => 'customer', 'value' => $p->customer, 'filters' => $filters, 'class' => 'text-gray-900 hover:text-blue-600 hover:underline'])
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @include('produksi.partials.worker-cell', ['worker' => $p->jahit, 'type' => 'jahit', 'date' => $p->jahit_date])
                        </td>
                        <td class="px-4 py-3 text-center text-sm whitespace-nowrap">
                            @if($can['assign_qc'])
                            <form method="POST" action="{{ route('produksi.assign-qc', $p->id) }}" class="inline-block min-w-[120px]">
                                @csrf
                                @method('PATCH')
                                <select name="qc_id" onchange="this.form.submit()" class="w-full max-w-[140px] rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium">
                                    <option value="">— QC —</option>
                                    @foreach($qcList as $qc)
                                    <option value="{{ $qc->id }}" @selected($p->qc_id == $qc->id)>{{ $qc->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @elseif($p->qc)
                            @include('produksi.partials.worker-cell', ['worker' => $p->qc, 'type' => 'qc', 'date' => $p->qc_date])
                            @else
                            <span class="font-medium text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm whitespace-nowrap">
                            @if($can['assign_pritil'])
                            <form method="POST" action="{{ route('produksi.assign-pritil', $p->id) }}" class="inline-block min-w-[120px]">
                                @csrf
                                @method('PATCH')
                                <select name="pritil_id" onchange="this.form.submit()" class="w-full max-w-[140px] rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium">
                                    <option value="">— Pritil —</option>
                                    @foreach($pritilList as $pritil)
                                    <option value="{{ $pritil->id }}" @selected($p->pritil_id == $pritil->id)>{{ $pritil->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @elseif($p->pritil)
                            @include('produksi.partials.worker-cell', ['worker' => $p->pritil, 'type' => 'pritil', 'date' => $p->pritil_date])
                            @else
                            <span class="font-medium text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($isGudangOrBoth && $p->transaction_id)
                                <a href="{{ route('transactions.show', $p->transaction_id) }}" class="text-sm font-bold text-blue-600 hover:underline">{{ $p->invoice }}</a>
                            @elseif($isGudangOrBoth)
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
                    <tr><td colspan="13" class="bg-white px-6 py-12 text-center text-sm text-gray-500">No records found. Adjust your filters to see more results.</td></tr>
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
                    <div class="relative" x-data="asyncCombobox({ endpoint: '{{ route('items.index') }}', additionalParams: { json: '1', type: '1' }, placeholder: 'Search manufactured item...', hiddenField: 'update_item_id' })">
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
