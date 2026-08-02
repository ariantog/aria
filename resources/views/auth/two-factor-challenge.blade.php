@extends('layouts.guest')

@section('title', 'Two-Factor Authentication')
@section('card-title', 'Two-Factor Authentication')
@section('card-description', 'Confirm access to your account.')

@section('card')
    <div x-data="{ recovery: false }" class="space-y-6">
        <div class="space-y-2 text-center">
            <h2 class="text-lg font-medium" x-text="recovery ? 'Recovery Code' : 'Authentication Code'"></h2>
            <p class="text-sm text-gray-500"
               x-text="recovery
                    ? 'Please confirm access to your account by entering one of your emergency recovery codes.'
                    : 'Enter the authentication code provided by your authenticator application.'"></p>
        </div>

        <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
            @csrf

            {{-- Authentication code --}}
            <div x-show="!recovery" class="flex flex-col items-center justify-center space-y-3 text-center">
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                       autocomplete="one-time-code" placeholder="000000"
                       :disabled="recovery" x-ref="codeInput"
                       class="w-40 rounded-md border border-gray-300 px-3 py-2 text-center text-lg tracking-[0.5em] shadow-sm">
                @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Recovery code --}}
            <div x-show="recovery" x-cloak class="space-y-2">
                <input type="text" name="recovery_code" placeholder="Enter recovery code"
                       :disabled="!recovery" x-ref="recoveryInput"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm">
                @error('recovery_code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                    class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Continue
            </button>

            <div class="text-center text-sm text-gray-500">
                <span>or you can </span>
                <button type="button" @click="recovery = !recovery; $nextTick(() => { (recovery ? $refs.recoveryInput : $refs.codeInput)?.focus() })"
                        class="cursor-pointer text-gray-900 underline underline-offset-4 hover:decoration-2"
                        x-text="recovery ? 'login using an authentication code' : 'login using a recovery code'"></button>
            </div>
        </form>
    </div>
@endsection
