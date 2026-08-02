@extends('layouts.guest')

@section('title', 'Forgot password')
@section('card-title', 'Forgot password')
@section('card-description', 'Enter your email to receive a password reset link')

@section('card')
    @if (session('status'))
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="grid gap-2">
                <label for="email" class="text-sm font-medium">Email address</label>
                <input id="email" type="email" name="email" autocomplete="off" autofocus
                       placeholder="email@example.com" value="{{ old('email') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="my-6 flex items-center justify-start">
                <button type="submit" data-test="email-password-reset-link-button"
                        class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Email password reset link
                </button>
            </div>
        </form>

        <div class="space-x-1 text-center text-sm text-gray-500">
            <span>Or, return to</span>
            <a href="{{ route('login') }}" class="text-blue-600 hover:underline">log in</a>
        </div>
    </div>
@endsection
