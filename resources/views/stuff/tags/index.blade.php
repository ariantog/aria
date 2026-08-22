@extends('layouts.app')

@section('title', 'Tags')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => route('tags.index')],
    ['title' => 'Tags', 'href' => route('tags.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4" x-data="tagsIndex()">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Tags</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage tags used across items.</p>
        </div>
        @if($can['create'])
        <button type="button" @click="openCreate()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Tag
        </button>
        @endif
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('tags.index') }}" class="flex items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex-1 max-w-sm">
            <label class="mb-1 block text-xs font-medium uppercase text-gray-500">Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or code…"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
        </div>
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        <a href="{{ route('tags.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Code</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Item Type</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($tags as $tag)
                    @php
                        $itemTypeLabel = array_search($tag->item_type, $itemTypes, true);
                        $filterUrl = $tag->itemsIndexFilterUrl();
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4 font-semibold text-gray-900">
                            <a href="{{ $filterUrl }}" class="text-blue-600 hover:text-blue-800 hover:underline" title="View tagged {{ $tag->filterItemType() === \App\Enums\ItemType::ASSET_LANCAR ? 'asset lancar' : 'items' }}">{{ $tag->name }}</a>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $tag->code ?: '-' }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-bold uppercase text-indigo-700">{{ $types[$tag->type] ?? $tag->type }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-bold uppercase text-gray-600">{{ $itemTypeLabel !== false ? $itemTypeLabel : 'All' }}</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                @if($can['edit'])
                                @php $tagPayload = json_encode(['id' => $tag->id, 'name' => $tag->name, 'code' => $tag->code, 'type' => (string) $tag->type, 'item_type' => (string) $tag->item_type], JSON_HEX_APOS | JSON_HEX_QUOT); @endphp
                                <button type="button" @click='openEdit({!! $tagPayload !!})'
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-900" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                @endif
                                @if($can['delete'])
                                <form method="POST" action="{{ route('tags.destroy', $tag->id) }}" onsubmit="return confirm('Delete this tag?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600" title="Delete">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">No tags found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $tags, 'label' => 'tags'])
    </div>

    {{-- Create/Edit modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @keydown.escape.window="showModal = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.away="showModal = false">
            <h3 class="text-lg font-bold text-gray-900" x-text="mode === 'edit' ? 'Edit Tag' : 'Create Tag'"></h3>
            <form :method="'POST'" :action="formAction" class="mt-4 space-y-4">
                @csrf
                <template x-if="mode === 'edit'">
                    <input type="hidden" name="_method" value="PUT">
                </template>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" x-model="form.name" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Code</label>
                    <input type="text" name="code" x-model="form.code" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Type <span class="text-red-500">*</span></label>
                    <select name="type" x-model="form.type" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="">Select type</option>
                        @foreach($types as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Item Type</label>
                    <select name="item_type" x-model="form.item_type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        @foreach($itemTypes as $label => $id)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800" x-text="mode === 'edit' ? 'Save Changes' : 'Create Tag'"></button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function tagsIndex() {
    return {
        showModal: false,
        mode: 'create',
        form: { id: null, name: '', code: '', type: '', item_type: '0' },
        get formAction() {
            return this.mode === 'edit' && this.form.id ? `/tags/${this.form.id}` : '/tags';
        },
        openCreate() {
            this.mode = 'create';
            this.form = { id: null, name: '', code: '', type: '', item_type: '0' };
            this.showModal = true;
        },
        openEdit(tag) {
            this.mode = 'edit';
            this.form = {
                id: tag.id,
                name: tag.name || '',
                code: tag.code || '',
                type: String(tag.type ?? ''),
                item_type: String(tag.item_type ?? '0'),
            };
            this.showModal = true;
        },
    };
}
</script>
@endpush
@endsection
