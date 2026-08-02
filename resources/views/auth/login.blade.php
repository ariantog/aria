@extends('layouts.guest')

@section('title', 'Log in')
@section('card-title', 'Log in to your account')
@section('card-description', 'Enter your username and password below to log in')

@section('card')
    @if (session('status'))
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
        @csrf

        <div class="grid gap-6">
            <div class="grid gap-2">
                <label for="username" class="text-sm font-medium">Username</label>
                <input id="username" type="text" name="username" required autofocus tabindex="1"
                       autocomplete="username" placeholder="username" value="{{ old('username') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('username')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <div class="flex items-center">
                    <label for="password" class="text-sm font-medium">Password</label>
                    @if ($canResetPassword ?? false)
                        <a href="{{ route('password.request') }}" tabindex="5" class="ml-auto text-sm text-blue-600 hover:underline">Forgot password?</a>
                    @endif
                </div>
                <input id="password" type="password" name="password" required tabindex="2"
                       autocomplete="current-password" placeholder="Password"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center space-x-3">
                <input id="remember" name="remember" type="checkbox" tabindex="3" class="h-4 w-4 rounded border-gray-300">
                <label for="remember" class="text-sm font-medium">Remember me</label>
            </div>

            <button type="submit" tabindex="4" data-test="login-button"
                    class="mt-4 w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Log in
            </button>
        </div>

        @if ($canRegister ?? false)
            <div class="text-center text-sm text-gray-500">
                Don't have an account?
                <a href="{{ route('register') }}" tabindex="5" class="text-blue-600 hover:underline">Sign up</a>
            </div>
        @endif
    </form>
@endsection
