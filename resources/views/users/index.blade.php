@extends('layouts.app')

@section('title', 'Users')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Users', 'href' => route('users.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    {{-- Header --}}
    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Users</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage your team members and their account permissions.</p>
        </div>
        @if($can['create_user'])
        <a href="{{ route('users.create') }}"
           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
        </a>
        @endif
    </div>

    <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-2 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex min-w-[12rem] flex-1 flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Search</label>
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or username"
                   class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium uppercase text-gray-500">Status</label>
            <select name="status" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <option value="active" @selected(($filters['status'] ?? 'active') === 'active')>Active only</option>
                <option value="banned" @selected(($filters['status'] ?? '') === 'banned')>Banned only</option>
                <option value="all" @selected(($filters['status'] ?? '') === 'all')>All users</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
            <a href="{{ route('users.index') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Username</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
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
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">
                            @if($user->location)
                                @if($can['edit_location'] ?? false)
                                    <a href="{{ route('locations.customers', $user->location->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline">{{ $user->location->name }}</a>
                                @else
                                    {{ $user->location->name }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    @if($can['edit_role'] ?? false)
                                        <a href="{{ route('roles.edit', $role->id) }}"
                                           class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200">{{ $role->name }}</a>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700">{{ $role->name }}</span>
                                    @endif
                                @empty
                                    <span class="text-xs italic text-gray-400">No roles</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $user->active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                <span class="text-gray-600">{{ $user->active ? 'Active' : 'Banned' }}</span>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">{{ $user->created_at?->format('d/m/Y') }}</td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                @if($can['edit_user'])
                                <a href="{{ route('users.edit', $user->id) }}"
                                   class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-900" title="Edit">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </a>
                                @endif
                                @if($can['edit_user'] && ! $user->is_superadmin && $user->id !== auth()->id())
                                    @if($user->active)
                                    <form method="POST" action="{{ route('users.ban', $user->id) }}" onsubmit="return confirm('Ban this user? They will no longer be able to log in, but their history is preserved.')">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600" title="Ban user">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                        </button>
                                    </form>
                                    @else
                                    <form method="POST" action="{{ route('users.unban', $user->id) }}" onsubmit="return confirm('Unban this user? They will be able to log in again.')">
                                        @csrf
                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-emerald-50 hover:text-emerald-600" title="Unban user">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </button>
                                    </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pagination', ['paginator' => $users, 'label' => 'users'])
    </div>
</div>
@endsection
