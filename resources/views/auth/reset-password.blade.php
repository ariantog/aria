@extends('layouts.guest')

@section('title', 'Reset password')
@section('card-title', 'Reset password')
@section('card-description', 'Please enter your new password below')

@section('card')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="grid gap-6">
            <div class="grid gap-2">
                <label for="email" class="text-sm font-medium">Email</label>
                <input id="email" type="email" name="email" autocomplete="email" readonly
                       value="{{ old('email', $email) }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm shadow-sm">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" type="password" name="password" autocomplete="new-password" autofocus
                       placeholder="Password"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       autocomplete="new-password" placeholder="Confirm password"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password_confirmation')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" data-test="reset-password-button"
                    class="mt-4 w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Reset password
            </button>
        </div>
    </form>
@endsection
