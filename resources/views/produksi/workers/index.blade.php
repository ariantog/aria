@extends('layouts.app')

@section('title', $title)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Produksi', 'href' => route('produksi.index')],
    ['title' => $title, 'href' => route('produksi.'.$type.'.index')],
];
@endphp

<div class="flex flex-col gap-4 p-4" x-data="workersPage()">
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $title }}</h2>
            <p class="mt-1 text-sm text-gray-500">Total {{ $workers->total() }} workers found</p>
        </div>
        @if($can['create_worker'])
        <button @click="openCreate()" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Worker
        </button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50/50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="w-[100px] px-4 py-3 font-medium">ID</th>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Joined At</th>
                    <th class="px-4 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($workers as $worker)
                <tr class="hover:bg-gray-50/50">
                    <td class="px-4 py-3 font-medium">{{ $worker->id }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100">
                                <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 100-8 4 4 0 000 8z"/></svg>
                            </div>
                            <a href="{{ route('produksi.'.$type.'.show', $worker->id) }}" class="font-bold text-blue-600 hover:underline">{{ $worker->name }}</a>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $worker->created_at?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3">
                        <div class="flex justify-end gap-2">
                            @if($can['edit_worker'])
                            <button @click="openEdit({{ $worker->id }}, @js($worker->name))" class="flex h-8 w-8 items-center justify-center rounded-md border border-blue-100 text-blue-600 hover:bg-blue-50">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            @endif
                            @if($can['delete_worker'])
                            <form method="POST" action="{{ route('produksi.'.$type.'.destroy', $worker->id) }}" onsubmit="return confirm('Are you sure you want to delete this {{ $type }} worker?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-md border border-red-100 text-red-600 hover:bg-red-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="h-24 px-4 text-center text-gray-500">No workers found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $workers, 'label' => 'workers'])
    </div>

    {{-- Modal --}}
    <div x-show="isOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="isOpen=false">
        <div @click.away="isOpen=false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <h3 class="mb-4 text-lg font-semibold" x-text="editingId ? 'Edit Worker' : 'Add Worker'"></h3>
            <form :action="formAction" method="POST" class="space-y-4">
                @csrf
                <template x-if="editingId"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid gap-2">
                    <label class="text-sm font-medium">Name</label>
                    <input name="name" x-model="name" placeholder="Worker's name" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="isOpen=false" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700" x-text="editingId ? 'Save changes' : 'Add worker'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function workersPage() {
    return {
        isOpen: false,
        editingId: null,
        name: '',
        storeUrl: '{{ route('produksi.'.$type.'.store') }}',
        updateBase: '{{ url('produksi/'.$type) }}',
        get formAction() { return this.editingId ? `${this.updateBase}/${this.editingId}` : this.storeUrl; },
        openCreate() { this.editingId = null; this.name = ''; this.isOpen = true; },
        openEdit(id, name) { this.editingId = id; this.name = name; this.isOpen = true; },
    };
}
</script>
@endpush
@endsection
