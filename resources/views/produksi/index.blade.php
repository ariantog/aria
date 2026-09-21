@extends('layouts.app')

@section('title', 'Production')

@section('content')
@php
$breadcrumbs = [['title' => 'Produksi', 'href' => route('produksi.index')]];
$f = $filters;
@endphp

<div class="flex flex-col gap-2 overflow-x-auto p-2 sm:p-4">
    <div class="mb-4 flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Produksi</h2>
            <p class="mt-1 text-sm text-gray-500">List of production records at the cutting stage.</p>
        </div>
        @if($can['create_produksi'])
        <a href="{{ route('produksi.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 sm:w-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Production Entry
        </a>
        @endif
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('produksi.index') }}" class="mb-2 flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">From</label><input type="date" name="from" value="{{ $f['from'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">To</label><input type="date" name="to" value="{{ $f['to'] ?? '' }}" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">Kode</label><input type="text" name="kode" value="{{ $f['kode'] ?? '' }}" placeholder="Kode…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">Customer</label><input type="text" name="customer" value="{{ $f['customer'] ?? '' }}" placeholder="Customer…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">Serial</label><input type="text" name="serial" value="{{ $f['serial'] ?? '' }}" placeholder="Serial…" class="w-24 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">SJP</label><input type="text" name="surat_jalan_potong" value="{{ $f['surat_jalan_potong'] ?? '' }}" placeholder="SJP…" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1"><label class="text-xs font-medium uppercase text-gray-500">Warna</label><input type="text" name="warna" value="{{ $f['warna'] ?? '' }}" placeholder="Warna…" class="w-24 rounded-md border border-gray-300 px-2.5 py-1.5 text-sm"></div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Potong</label>
            <select name="potong_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($potongList as $w)<option value="{{ $w->id }}" @selected(($f['potong_id'] ?? '') == $w->id)>{{ $w->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Jahit</label>
            <select name="jahit_id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm">
                <option value="">All</option>
                @foreach($jahitList as $w)<option value="{{ $w->id }}" @selected(($f['jahit_id'] ?? '') == $w->id)>{{ $w->name }}</option>@endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('produksi.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        @foreach(['Kitir','Parent','Kode','Jumlah','SJP','Potong','Size','Warna','Customer'] as $h)
                        <th class="px-4 py-3 text-left text-xs font-bold uppercase text-gray-900 whitespace-nowrap">{{ $h }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase text-gray-900 whitespace-nowrap">Jahit</th>
                        <th class="px-4 py-3 text-center text-xs font-bold uppercase text-gray-900 whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($prod_produksi as $p)
                    <tr class="transition-colors hover:bg-gray-50/50">
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap text-blue-600">
                            <a href="{{ route('produksi.edit', $p->id) }}" class="hover:underline">{{ $p->serial }}</a>
                        </td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @if($p->original_id)
                            @include('partials.filter-link', ['route' => 'produksi.index', 'param' => 'serial', 'value' => $p->parentSerial(), 'filters' => $f])
                            @else
                            <span class="font-medium text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 rounded-md border border-blue-200 bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $p->temp_name }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-black whitespace-nowrap tabular-nums text-gray-900">{{ $p->quantity }}</td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @include('partials.filter-link', ['route' => 'produksi.index', 'param' => 'surat_jalan_potong', 'value' => $p->surat_jalan_potong, 'filters' => $f, 'class' => 'text-gray-900 hover:text-blue-600 hover:underline'])
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @include('produksi.partials.worker-cell', ['worker' => $p->potong, 'type' => 'potong', 'date' => $p->potong_date])
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap"><span class="w-fit rounded border border-gray-300 bg-white px-2 py-0.5 font-mono text-[10px]">{{ $p->size->name ?? '-' }}</span></td>
                        <td class="px-4 py-3 text-xs font-bold whitespace-nowrap text-gray-600">{{ $p->warna ?: '-' }}</td>
                        <td class="px-4 py-3 text-sm font-bold whitespace-nowrap">
                            @include('partials.filter-link', ['route' => 'produksi.index', 'param' => 'customer', 'value' => $p->customer, 'filters' => $f, 'class' => 'text-gray-900 hover:text-blue-600 hover:underline'])
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if($p->jahit_date)
                            @include('produksi.partials.worker-cell', ['worker' => $p->jahit, 'type' => 'jahit', 'date' => $p->jahit_date])
                            @elseif($can['setor_produksi'])
                            <form method="POST" action="{{ route('produksi.assign-jahit', $p->id) }}" class="mx-auto w-full max-w-[140px]">
                                @csrf
                                @method('PATCH')
                                <select name="jahit_id" onchange="this.form.submit()" data-testid="assign-jahit-{{ $p->id }}" class="w-full rounded border border-emerald-200 bg-white px-1 py-0.5 text-xs font-medium text-emerald-800">
                                    <option value="">— Jahit —</option>
                                    @foreach($jahitList as $w)
                                    <option value="{{ $w->id }}" @selected($p->jahit_id == $w->id)>{{ $w->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @else
                            <span class="font-medium text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($can['edit_produksi'])
                                <a href="{{ route('produksi.edit', $p->id) }}" title="Edit" class="flex h-7 w-7 items-center justify-center rounded-md border border-gray-200 text-gray-600 hover:bg-gray-50">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($can['setor_produksi'] && $p->jahit_date)
                                <form method="POST" action="{{ route('produksi.setor', $p->id) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" title="Setor ke Jahit" class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-600 text-white shadow-sm hover:bg-emerald-700">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="bg-white px-6 py-12 text-center text-sm text-gray-500">No production records found.</td></tr>
                    @endforelse
                </tbody>
                @if($prod_produksi->isNotEmpty())
                <tfoot class="border-t border-gray-200 bg-gray-50">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-900">Total</td>
                        <td class="px-4 py-3 text-sm font-black whitespace-nowrap tabular-nums text-gray-900" data-testid="produksi-page-jumlah-total">{{ $prod_produksi->sum('quantity') }}</td>
                        <td colspan="7"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $prod_produksi, 'label' => 'records'])
    </div>

</div>
@endsection
