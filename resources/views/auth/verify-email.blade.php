@extends('layouts.guest')

@section('title', 'Email verification')
@section('card-title', 'Verify email')
@section('card-description', 'Please verify your email address by clicking on the link we just emailed to you.')

@section('card')
    @if (session('status') === 'verification-link-sent')
        <div class="mb-4 text-center text-sm font-medium text-green-600">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <form method="POST" action="{{ route('verification.send') }}" class="space-y-6 text-center">
        @csrf
        <button type="submit"
                class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-900 hover:bg-gray-200">
            Resend verification email
        </button>

        <a href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
           class="mx-auto block text-sm text-blue-600 hover:underline">
            Log out
        </a>
    </form>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
@endsection
