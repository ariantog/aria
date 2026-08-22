@extends('layouts.app')

@section('title', $role->name)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Roles', 'href' => route('roles.index')],
    ['title' => $role->name, 'href' => route('roles.show', $role->id)],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $role->name }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">Users assigned to this role.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($can['edit_role'])
            <a href="{{ route('roles.edit', $role->id) }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                Edit Role
            </a>
            @endif
            <a href="{{ route('roles.index') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                Back to Roles
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h3 class="text-sm font-semibold text-gray-900">Users ({{ $users->total() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="font-semibold text-gray-900">{{ $user->name }}</div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ '@'.$user->username }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $user->location?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $user->active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                <span class="text-gray-600">{{ $user->active ? 'Active' : 'Banned' }}</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $user->created_at?->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            @if($can['edit_user'])
                            <a href="{{ route('users.edit', $user->id) }}"
                               class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-900" title="Edit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500">No users assigned to this role.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $users, 'label' => 'users'])
    </div>
</div>
@endsection
