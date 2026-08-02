@extends('layouts.guest')

@section('title', 'Confirm password')
@section('card-title', 'Confirm your password')
@section('card-description', 'This is a secure area of the application. Please confirm your password before continuing.')

@section('card')
    <form method="POST" action="{{ route('password.confirm.store') }}">
        @csrf
        <div class="space-y-6">
            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" type="password" name="password" placeholder="Password"
                       autocomplete="current-password" autofocus
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center">
                <button type="submit" data-test="confirm-password-button"
                        class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Confirm password
                </button>
            </div>
        </div>
    </form>
@endsection
