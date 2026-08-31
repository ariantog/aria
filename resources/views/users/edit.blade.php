@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Users', 'href' => route('users.index')],
    ['title' => 'Edit User', 'href' => route('users.edit', $editUser->id)],
];
$currentRole = old('role', $userRoles->first());
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Edit User: <span class="text-blue-600">{{ $editUser->name }}</span></h2>
        <p class="mt-0.5 text-sm text-gray-500">Manage user details, role, and account status.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('users.update', $editUser->id) }}" class="p-6"
              x-data="{ active: {{ old('active', $editUser->active) ? 'true' : 'false' }}, resetPw: false }">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $editUser->name) }}" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username', $editUser->username) }}" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('username')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Role Assignment <span class="text-red-500">*</span></label>
                    <select name="role" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="">Select a role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected($currentRole === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Location</label>
                    <select name="location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="" @selected((int) old('location_id', $editUser->location_id) <= 0)>No Location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id', $editUser->location_id) == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @include('users.partials.staff-roles', [
                'staffRoles' => $staffRoles,
                'assignedStaffRoleIds' => $assignedStaffRoleIds ?? [],
                'canAssignStaffRoles' => $canAssignStaffRoles ?? false,
            ])

            {{-- Danger Zone --}}
            <div class="mt-8 border-t border-gray-200 pt-6">
                <div class="rounded-lg border border-red-200 bg-red-50 p-5">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.75-2.98l-7.07-12a2 2 0 00-3.5 0l-7.07 12A2 2 0 004.93 19z"/></svg>
                        <div class="flex-1 space-y-4">
                            <div>
                                <h3 class="text-base font-semibold text-red-600">Danger Zone</h3>
                                <p class="text-sm text-gray-500">Actions here can affect the user's ability to access the system.</p>
                            </div>

                            {{-- Reset Password --}}
                            <div class="flex items-center justify-between rounded-lg border border-red-100 bg-white p-4">
                                <div>
                                    <div class="font-medium text-gray-900">Reset Password</div>
                                    <div class="text-sm text-gray-500">Set a new password for this user.</div>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <button type="button" @click="resetPw = !resetPw"
                                            :class="resetPw ? 'bg-blue-600' : 'bg-gray-300'"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                        <span :class="resetPw ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                    </button>
                                    <span class="text-sm font-medium" :class="resetPw ? 'text-blue-600' : 'text-gray-500'" x-text="resetPw ? 'Change on save' : 'No change'"></span>
                                </label>
                            </div>
                            <div x-show="resetPw" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">New Password</label>
                                    <input type="password" name="password" :disabled="!resetPw"
                                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password</label>
                                    <input type="password" name="password_confirmation" :disabled="!resetPw"
                                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                                </div>
                            </div>

                            {{-- Ban / Active --}}
                            <div class="flex items-center justify-between rounded-lg border border-red-100 bg-white p-4">
                                <div>
                                    <div class="font-medium text-gray-900">Account Status</div>
                                    <div class="text-sm text-gray-500">If banned, this user cannot log in.</div>
                                </div>
                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="hidden" name="active" :value="active ? 1 : 0">
                                    <button type="button" @click="active = !active"
                                            :class="active ? 'bg-emerald-600' : 'bg-red-500'"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                                        <span :class="active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                                    </button>
                                    <span class="text-sm font-medium" :class="active ? 'text-emerald-600' : 'text-red-500'" x-text="active ? 'Active' : 'Banned'"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('users.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
