@extends('layouts.guest')

@section('title', 'Register')
@section('card-title', 'Create an account')
@section('card-description', 'Enter your details below to create your account')

@section('card')
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
        @csrf

        <div class="grid gap-6">
            <div class="grid gap-2">
                <label for="name" class="text-sm font-medium">Name</label>
                <input id="name" type="text" name="name" required autofocus tabindex="1"
                       autocomplete="name" placeholder="Full name" value="{{ old('name') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="username" class="text-sm font-medium">Username</label>
                <input id="username" type="text" name="username" required tabindex="2"
                       autocomplete="username" placeholder="Username" value="{{ old('username') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('username')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="email" class="text-sm font-medium">Email address</label>
                <input id="email" type="email" name="email" required tabindex="2"
                       autocomplete="email" placeholder="email@example.com" value="{{ old('email') }}"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-medium">Password</label>
                <input id="password" type="password" name="password" required tabindex="3"
                       autocomplete="new-password" placeholder="Password"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-medium">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required tabindex="4"
                       autocomplete="new-password" placeholder="Confirm password"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('password_confirmation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" tabindex="5" data-test="register-user-button"
                    class="mt-2 w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Create account
            </button>
        </div>

        <div class="text-center text-sm text-gray-500">
            Already have an account?
            <a href="{{ route('login') }}" tabindex="6" class="text-blue-600 hover:underline">Log in</a>
        </div>
    </form>
@endsection
