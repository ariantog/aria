@extends('layouts.app')

@section('title', 'Deleted Roles')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Roles', 'href' => route('roles.index')],
    ['title' => 'Deleted', 'href' => route('roles.deleted.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Deleted Roles</h2>
            <p class="mt-0.5 text-sm text-gray-500">Soft-deleted roles can be restored from here.</p>
        </div>
        <a href="{{ route('roles.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
            Back to Roles
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Permissions</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Deleted At</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="font-semibold text-gray-900">{{ $role->name }}</div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $role->permissions->count() }} permissions</span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $role->deleted_at?->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            @if($can['restore_role'])
                            <form method="POST" action="{{ route('roles.restore', $role->id) }}" onsubmit="return confirm('Restore this role?')">
                                @csrf
                                <button type="submit" class="rounded-md border border-emerald-200 px-3 py-1.5 text-sm font-medium text-emerald-700 hover:bg-emerald-50">
                                    Restore
                                </button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500">No deleted roles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $roles, 'label' => 'roles'])
    </div>
</div>
@endsection
