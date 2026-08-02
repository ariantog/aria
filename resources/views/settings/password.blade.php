@extends('layouts.app')
@section('title', 'Password settings')

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Password settings', 'href' => route('user-password.edit')],
        ];
    @endphp

    @include('settings.partials.nav')
@endsection

@section('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Update password</h2>
            <p class="text-sm text-gray-500">Ensure your account is using a long, random password to stay secure</p>
        </header>

        <form method="POST" action="{{ route('user-password.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid gap-2">
                <label for="current_password" class="text-sm font-medium">Current password</label>
                <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                       placeholder="Current password"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('current_password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium">New password</label>
                <input id="password" name="password" type="password" autocomplete="new-password"
                       placeholder="New password"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                       placeholder="Confirm password"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password_confirmation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" data-test="update-password-button"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save password
                </button>
                @if (session('success') || session('status') === 'password-updated')
                    <p class="text-sm text-neutral-600">Saved</p>
                @endif
            </div>
        </form>
    </div>
@endsection
