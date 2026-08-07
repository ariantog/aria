@extends('layouts.app')
@section('title', 'Profile settings')

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Profile settings', 'href' => route('profile.edit')],
        ];
        $user = auth()->user();
    @endphp

    @include('settings.partials.nav')
@endsection

@section('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Profile information</h2>
            <p class="text-sm text-gray-500">Update your name and email address</p>
        </header>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-6">
            @csrf
            @method('PATCH')

            <div class="grid gap-2">
                <label for="name" class="text-sm font-medium">Name</label>
                <input id="name" name="name" required autocomplete="name" placeholder="Full name"
                       value="{{ old('name', $user->name) }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-2">
                <label for="email" class="text-sm font-medium">Email address</label>
                <input id="email" type="email" name="email" required autocomplete="username" placeholder="Email address"
                       value="{{ old('email', $user->email) }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            @if (($mustVerifyEmail ?? false) && $user->email_verified_at === null)
                <div>
                    <p class="-mt-4 text-sm text-gray-500">
                        Your email address is unverified.
                        <button form="resend-verification" type="submit"
                                class="text-gray-900 underline underline-offset-4 hover:decoration-2">
                            Click here to resend the verification email.
                        </button>
                    </p>
                    @if (session('status') === 'verification-link-sent')
                        <div class="mt-2 text-sm font-medium text-green-600">
                            A new verification link has been sent to your email address.
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex items-center gap-4">
                <button type="submit" data-test="update-profile-button"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save
                </button>
                @if (session('status') === 'profile-updated' || session('success'))
                    <p class="text-sm text-neutral-600">Saved</p>
                @endif
            </div>
        </form>

        @if (($mustVerifyEmail ?? false) && $user->email_verified_at === null)
            <form id="resend-verification" method="POST" action="{{ route('verification.send') }}" class="hidden">
                @csrf
            </form>
        @endif
    </div>
@endsection
