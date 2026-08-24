@extends('layouts.app')
@section('title', 'Two-Factor Authentication')

@push('settings-content')
    <div class="space-y-6"
         x-data="twoFactor({{ ($twoFactorEnabled ?? false) ? 'true' : 'false' }}, {{ ($requiresConfirmation ?? false) ? 'true' : 'false' }})">
        <header>
            <h2 class="text-base font-medium text-gray-900">Two-Factor Authentication</h2>
            <p class="text-sm text-gray-500">Manage your two-factor authentication settings</p>
        </header>

        {{-- ENABLED --}}
        <template x-if="enabled">
            <div class="flex flex-col items-start justify-start space-y-4">
                <span class="inline-flex items-center rounded-md bg-gray-900 px-2 py-0.5 text-xs font-medium text-white">Enabled</span>
                <p class="text-gray-500">
                    With two-factor authentication enabled, you will be prompted for a secure, random pin during login,
                    which you can retrieve from the TOTP-supported application on your phone.
                </p>

                {{-- Recovery codes --}}
                <div class="w-full rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">2FA Recovery Codes</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.
                            </p>
                        </div>
                        <button type="button" @click="toggleRecoveryCodes()"
                                class="flex-shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <span x-text="codesVisible ? 'Hide' : 'View'"></span> Codes
                        </button>
                    </div>

                    <div x-show="codesVisible" x-cloak class="mt-4 space-y-3">
                        <div class="grid grid-cols-1 gap-1 rounded-md bg-gray-50 p-3 font-mono text-sm sm:grid-cols-2">
                            <template x-for="code in recoveryCodes" :key="code">
                                <div x-text="code" class="select-text"></div>
                            </template>
                        </div>
                        <button type="button" @click="regenerateCodes()"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Regenerate Codes
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('two-factor.disable') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Disable 2FA
                    </button>
                </form>
            </div>
        </template>

        {{-- DISABLED --}}
        <template x-if="!enabled">
            <div class="flex flex-col items-start justify-start space-y-4">
                <span class="inline-flex items-center rounded-md bg-red-600 px-2 py-0.5 text-xs font-medium text-white">Disabled</span>
                <p class="text-gray-500">
                    When you enable two-factor authentication, you will be prompted for a secure pin during login.
                    This pin can be retrieved from a TOTP-supported application on your phone.
                </p>

                <div>
                    <button type="button" @click="enable()"
                            class="inline-flex items-center gap-1.5 rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span x-text="hasSetupData ? 'Continue Setup' : 'Enable 2FA'"></span>
                    </button>
                </div>
            </div>
        </template>

        {{-- SETUP MODAL --}}
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.window.escape="closeModal()">
            <div @click.away="closeModal()" class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                <div class="flex flex-col items-center text-center">
                    <div class="mb-3 rounded-full border border-gray-200 bg-white p-3 shadow-sm">
                        <svg class="h-6 w-6 text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5m-4.5 15h4.5m11.25-15h-4.5m4.5 15h-4.5M3.75 12h16.5"/></svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900" x-text="modalTitle"></h3>
                    <p class="mt-1 text-sm text-gray-500" x-text="modalDescription"></p>
                </div>

                <div class="mt-5 flex flex-col items-center space-y-5">
                    {{-- Setup step --}}
                    <template x-if="!verifyStep">
                        <div class="w-full space-y-5">
                            <div class="mx-auto flex max-w-md overflow-hidden">
                                <div class="mx-auto flex aspect-square w-64 items-center justify-center rounded-lg border border-gray-200 p-5">
                                    <template x-if="qrCodeSvg">
                                        <div class="aspect-square w-full rounded-lg bg-white p-2 [&_svg]:h-full [&_svg]:w-full" x-html="qrCodeSvg"></div>
                                    </template>
                                    <template x-if="!qrCodeSvg">
                                        <span class="text-sm text-gray-400">Loading…</span>
                                    </template>
                                </div>
                            </div>

                            <button type="button" @click="nextStep()"
                                    class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                                    x-text="modalButtonText"></button>

                            <div class="relative flex w-full items-center justify-center">
                                <div class="absolute inset-0 top-1/2 h-px w-full bg-gray-200"></div>
                                <span class="relative bg-white px-2 py-1 text-xs text-gray-500">or, enter the code manually</span>
                            </div>

                            <div class="flex w-full items-stretch overflow-hidden rounded-xl border border-gray-200">
                                <template x-if="manualSetupKey">
                                    <input type="text" readonly :value="manualSetupKey"
                                           class="h-full w-full bg-white p-3 text-gray-900 outline-none">
                                </template>
                                <template x-if="!manualSetupKey">
                                    <div class="flex h-full w-full items-center justify-center bg-gray-50 p-3 text-sm text-gray-400">Loading…</div>
                                </template>
                                <button type="button" @click="copyKey()" class="border-l border-gray-200 px-3 hover:bg-gray-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Verify step --}}
                    <template x-if="verifyStep">
                        <div class="w-full space-y-3">
                            <div class="flex flex-col items-center space-y-3 py-2">
                                <input type="text" x-model="confirmCode" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                       placeholder="000000"
                                       class="w-40 rounded-md border border-gray-300 px-3 py-2 text-center text-lg tracking-[0.5em] shadow-sm">
                                <p x-show="confirmError" x-text="confirmError" class="text-sm text-red-600"></p>
                            </div>
                            <div class="flex w-full space-x-5">
                                <button type="button" @click="verifyStep = false"
                                        class="flex-1 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</button>
                                <button type="button" @click="confirmSetup()" :disabled="confirmCode.length < 6"
                                        class="flex-1 rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 disabled:opacity-50">Confirm</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
@endpush

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Two-Factor Authentication', 'href' => route('two-factor.show')],
        ];
    @endphp

    @include('settings.partials.nav')
@endsection

@push('scripts')
<script>
function twoFactor(enabled, requiresConfirmation) {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
    };
    return {
        enabled,
        requiresConfirmation,
        showModal: false,
        verifyStep: false,
        qrCodeSvg: null,
        manualSetupKey: null,
        recoveryCodes: [],
        codesVisible: false,
        confirmCode: '',
        confirmError: '',

        get hasSetupData() { return this.qrCodeSvg !== null && this.manualSetupKey !== null; },
        get modalTitle() {
            if (this.enabled) return 'Two-Factor Authentication Enabled';
            if (this.verifyStep) return 'Verify Authentication Code';
            return 'Enable Two-Factor Authentication';
        },
        get modalDescription() {
            if (this.enabled) return 'Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.';
            if (this.verifyStep) return 'Enter the 6-digit code from your authenticator app';
            return 'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app';
        },
        get modalButtonText() { return this.enabled ? 'Close' : 'Continue'; },

        async enable() {
            if (this.hasSetupData) { this.showModal = true; return; }
            try {
                await fetch('{{ route('two-factor.enable') }}', { method: 'POST', headers });
                await this.fetchSetupData();
                this.showModal = true;
            } catch (e) { console.error(e); }
        },

        async fetchSetupData() {
            try {
                const [qr, key] = await Promise.all([
                    fetch('{{ route('two-factor.qr-code') }}', { headers }).then(r => r.json()),
                    fetch('{{ route('two-factor.secret-key') }}', { headers }).then(r => r.json()),
                ]);
                this.qrCodeSvg = qr.svg;
                this.manualSetupKey = key.secretKey;
            } catch (e) { console.error(e); }
        },

        nextStep() {
            if (this.enabled) { this.closeModal(); return; }
            if (this.requiresConfirmation) { this.verifyStep = true; return; }
            this.finishSetup();
        },

        async confirmSetup() {
            this.confirmError = '';
            try {
                const res = await fetch('{{ route('two-factor.confirm') }}', {
                    method: 'POST', headers, body: JSON.stringify({ code: this.confirmCode }),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    this.confirmError = (data.errors && (data.errors.code?.[0] || Object.values(data.errors)[0]?.[0])) || 'Invalid code.';
                    this.confirmCode = '';
                    return;
                }
                this.finishSetup();
            } catch (e) { this.confirmError = 'Something went wrong.'; }
        },

        finishSetup() {
            this.enabled = true;
            this.verifyStep = false;
            this.confirmCode = '';
            this.qrCodeSvg = null;
            this.manualSetupKey = null;
            this.closeModal();
        },

        closeModal() {
            this.showModal = false;
            this.verifyStep = false;
        },

        copyKey() {
            if (this.manualSetupKey) navigator.clipboard.writeText(this.manualSetupKey);
        },

        async toggleRecoveryCodes() {
            if (this.codesVisible) { this.codesVisible = false; return; }
            if (!this.recoveryCodes.length) { await this.fetchRecoveryCodes(); }
            this.codesVisible = true;
        },

        async fetchRecoveryCodes() {
            try {
                const codes = await fetch('{{ route('two-factor.recovery-codes') }}', { headers }).then(r => r.json());
                this.recoveryCodes = Array.isArray(codes) ? codes : [];
            } catch (e) { this.recoveryCodes = []; }
        },

        async regenerateCodes() {
            try {
                await fetch('{{ route('two-factor.regenerate-recovery-codes') }}', { method: 'POST', headers });
                await this.fetchRecoveryCodes();
            } catch (e) { console.error(e); }
        },
    };
}
</script>
@endpush
