@extends('layouts.app')

@section('title', 'Create User')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Users', 'href' => route('users.index')],
    ['title' => 'Create User', 'href' => route('users.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Create New User</h2>
        <p class="mt-0.5 text-sm text-gray-500">Deploy a new account with customized roles and permissions.</p>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('users.store') }}" class="p-6" x-data="{ active: {{ old('active', 1) ? 'true' : 'false' }} }">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Robert Fox"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Username <span class="text-red-500">*</span></label>
                    <input type="text" name="username" value="{{ old('username') }}" required placeholder="e.g. robert.fox"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('username')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Access Role <span class="text-red-500">*</span></label>
                    <select name="role" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="">Select a role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Location</label>
                    <select name="location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        <option value="">No Location</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between border-t border-gray-100 pt-6">
                <div>
                    <h4 class="font-medium text-gray-900">Account Status</h4>
                    <p class="mt-0.5 max-w-sm text-sm text-gray-500">If active, the user will be able to log in.</p>
                </div>
                <label class="flex cursor-pointer items-center gap-2">
                    <input type="hidden" name="active" :value="active ? 1 : 0">
                    <button type="button" @click="active = !active"
                            :class="active ? 'bg-blue-600' : 'bg-gray-300'"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors">
                        <span :class="active ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                    </button>
                    <span class="text-sm font-medium" :class="active ? 'text-blue-600' : 'text-gray-500'" x-text="active ? 'Active' : 'Inactive'"></span>
                </label>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('users.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Create User Account</button>
            </div>
        </form>
    </div>
</div>
@endsection
