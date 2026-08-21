@extends('layouts.app')

@section('title', $current_type ? ucfirst($current_type) . ' — Address Book' : 'Address Book')

@section('content')
@php
$baseUrl = \App\Models\Addrbook::typeIndexRoute($current_type);
$breadcrumbs = [
    ['title' => 'Address Book', 'href' => $baseUrl],
    ['title' => 'List', 'href' => $baseUrl],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Address Book</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage your customers and contacts efficiently.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($can['create'])
            <a href="{{ route('addrbook.type.create', $current_type) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add New
            </a>
            @endif
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ $baseUrl }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-1 min-w-[240px] flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Search Name / Contact / ID / Phone</label>
            <div class="relative">
                <svg class="absolute left-3 top-2.5 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search…"
                       class="w-full rounded-md border border-gray-300 py-1.5 pl-9 pr-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
            </div>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 uppercase">Status</label>
            <select name="trashed" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="" @selected(($filters['trashed'] ?? '') === '')>Active Only</option>
                <option value="with" @selected(($filters['trashed'] ?? '') === 'with')>With Deleted</option>
                <option value="only" @selected(($filters['trashed'] ?? '') === 'only')>Only Deleted</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ $baseUrl }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full table-fixed text-left text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-3 py-3 font-bold">Name / ID</th>
                    <th class="px-3 py-3 font-bold">Contact Info</th>
                    <th class="w-32 px-3 py-3 text-right font-bold">Connectivity</th>
                    <th class="w-36 px-3 py-3 text-right font-bold">Balance</th>
                    <th class="w-24 px-3 py-3 text-right font-bold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($customers as $a)
                    @php
                        $bal = (float) ($a->stat->balance ?? 0);
                        if (($can['bank_hidden_balance'] ?? false) && $a->type_slug === 'bank') $bal = 0;
                    @endphp
                    <tr class="align-top hover:bg-gray-50">
                        <td class="px-3 py-3">
                            <a href="/{{ $a->type_slug }}/{{ $a->id }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $a->name }}</a>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                ID: {{ $a->id }}
                                @if($a->deleted_at)<span class="rounded bg-rose-100 px-1 text-[10px] font-bold uppercase text-rose-700">Deleted</span>@endif
                            </div>
                            @if($a->contact_person)
                                <div class="mt-0.5 truncate text-xs text-gray-500" title="{{ $a->contact_person }}">{{ $a->contact_person }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-xs text-gray-500">
                            @if($a->phone)<div class="truncate" title="{{ $a->phone }}">☎ {{ $a->phone }}</div>@endif
                            @if($a->email)<div class="truncate" title="{{ $a->email }}">✉ {{ $a->email }}</div>@endif
                            @if($a->address)<div class="truncate" title="{{ $a->address }}">⚲ {{ $a->address }}</div>@endif
                            @if(! $a->phone && ! $a->email && ! $a->address)<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-3 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <span class="h-2 w-2 rounded-full {{ $a->is_online ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                                <span class="font-medium text-gray-700">{{ $a->is_online ? 'Online' : 'Offline' }}</span>
                            </div>
                            @if($a->ppn)<span class="mt-1 inline-block rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">PPN {{ $ppn_rate }}%</span>@endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-3 text-right font-medium text-gray-900">IDR {{ format_amount($bal) }}</td>
                        <td class="px-3 py-3">
                            <div class="flex justify-end gap-1">
                                @if($can['edit'])
                                <a href="/{{ $a->type_slug }}/{{ $a->id }}/edit" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-blue-50 hover:text-blue-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($can['delete'] && ! $a->deleted_at)
                                <button type="button" onclick="deleteAddrbook({{ $a->id }})" class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-rose-50 hover:text-rose-500">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-sm italic text-gray-500">No address book entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $customers, 'label' => 'entries'])
    </div>
</div>

@push('scripts')
<script>
const _CSRF = '{{ csrf_token() }}';
async function deleteAddrbook(id) {
    if (!confirm('Apakah Anda yakin ingin menghapus data ini?')) return;
    const res = await fetch(`/addrbook/${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': _CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: '_method=DELETE',
    });
    if (res.redirected) { window.location.href = res.url; } else { window.location.reload(); }
}
</script>
@endpush
@endsection
