@extends('layouts.app')

@section('title', $operation->name . ' - Accounts')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Journals', 'href' => route('operations.index')],
    ['title' => 'Operations', 'href' => route('operations.index')],
    ['title' => $operation->name, 'href' => route('operations.show', $operation->id)],
];
@endphp

<div class="flex flex-col gap-4 overflow-x-auto p-4" x-data="accCrud()">
    <div class="mb-4 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="flex items-center gap-4">
            <a href="{{ route('operations.index') }}" class="flex h-10 w-10 items-center justify-center rounded-md border border-gray-300 hover:bg-gray-50">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Operation: {{ $operation->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">Account list bound to this operation.</p>
            </div>
        </div>
        <button @click="openCreate()" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 sm:w-auto">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Account
        </button>
    </div>

    <form method="GET" action="{{ route('operations.show', $operation->id) }}" class="relative mb-2 max-w-sm">
        <svg class="absolute left-2.5 top-2.5 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search accounts in operation..." class="w-full rounded-md border border-gray-300 bg-gray-50 py-2 pl-9 pr-3 text-sm">
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50/50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Account Name</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($accounts as $acc)
                    <tr class="hover:bg-gray-50/50">
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100">
                                    <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                </div>
                                <div>
                                    <a href="{{ route('account-list.ledger', $acc->id) }}" class="text-sm font-bold text-gray-900 hover:text-blue-600">{{ $acc->name }}</a>
                                    <div class="text-xs text-gray-500">{{ $acc->description ?: 'No description' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('account-list.ledger', $acc->id) }}" class="inline-flex h-8 items-center gap-2 rounded-md border border-gray-300 px-3 text-sm hover:bg-gray-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Ledger
                                </a>
                                <button @click="openEdit({{ $acc->id }}, @js($acc->name), @js($acc->description))" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:text-gray-900">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('account-list.destroy', $acc->id) }}" onsubmit="return confirm('Are you sure you want to delete this account?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:text-red-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-6 py-12 text-center text-sm text-gray-500">No accounts found in this operation yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $accounts, 'label' => 'accounts'])
    </div>

    {{-- Dialog --}}
    <div x-show="isOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="isOpen=false">
        <div @click.away="isOpen=false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold" x-text="editingId ? 'Edit Account' : 'Add Account'"></h3>
            <form :action="formAction" method="POST" class="space-y-4">
                @csrf
                <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>
                <input type="hidden" name="operation_id" value="{{ $operation->id }}">
                <div class="space-y-2"><label class="text-sm font-medium">Account Name</label><input name="name" x-model="name" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></div>
                <div class="space-y-2"><label class="text-sm font-medium">Description</label><input name="description" x-model="description" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"></div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="isOpen=false" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" x-text="editingId ? 'Save Changes' : 'Create'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function accCrud() {
    return {
        isOpen: false, editingId: null, name: '', description: '',
        storeUrl: '{{ route('account-list.store') }}',
        base: '{{ url('journals/account-list') }}',
        get formAction() { return this.editingId ? `${this.base}/${this.editingId}` : this.storeUrl; },
        openCreate() { this.editingId = null; this.name = ''; this.description = ''; this.isOpen = true; },
        openEdit(id, name, desc) { this.editingId = id; this.name = name; this.description = desc || ''; this.isOpen = true; },
    };
}
</script>
@endpush
@endsection
