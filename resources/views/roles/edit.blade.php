@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Roles', 'href' => route('roles.index')],
    ['title' => 'Edit Role', 'href' => route('roles.edit', $role->id)],
];
$allPermissionNames = collect($permissions)->flatten(1)->pluck('name')->values()->all();
$initialSelected = old('permissions', $rolePermissions->values()->all());
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit Role: <span class="text-blue-600">{{ $role->name }}</span></h2>
        <p class="mt-0.5 text-sm text-gray-500">Update the role name and assigned permissions.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('roles.update', $role->id) }}" class="p-6"
              x-data="roleForm(@js($allPermissionNames), @js($initialSelected))">
            @csrf
            @method('PUT')
            <div class="mb-6 max-w-md">
                <label class="mb-1 block text-sm font-medium text-gray-700">Role Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $role->name) }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mb-6">
                <h3 class="mb-3 text-base font-semibold text-gray-900">Permissions</h3>
                @include('roles._matrix')
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-6">
                <a href="{{ route('roles.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function roleForm(allNames, initial) {
    return {
        allNames: allNames,
        selected: initial || [],
        toggleGroup(names, checked) {
            if (checked) {
                names.forEach(n => { if (!this.selected.includes(n)) this.selected.push(n); });
            } else {
                this.selected = this.selected.filter(n => !names.includes(n));
            }
        },
        groupAllSelected(names) {
            return names.length > 0 && names.every(n => this.selected.includes(n));
        },
        selectAll() {
            if (this.selected.length === this.allNames.length) {
                this.selected = [];
            } else {
                this.selected = [...this.allNames];
            }
        },
    };
}
</script>
@endpush
@endsection
