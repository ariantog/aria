@extends('layouts.app')
@section('title', 'Appearance settings')

@push('settings-content')
    <div class="space-y-6">
        <header>
            <h2 class="text-base font-medium text-gray-900">Appearance settings</h2>
            <p class="text-sm text-gray-500">Choose light, dark, or system theme. Saved to your account and synced across devices.</p>
        </header>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('appearance.update') }}" x-data="appearanceTabs(@js($appearance))" x-init="init()" @submit="syncBeforeSubmit()">
            @csrf
            @method('PATCH')
            <input type="hidden" name="appearance" :value="appearance">

            <div class="inline-flex gap-1 rounded-lg bg-gray-100 p-1">
                <template x-for="tab in tabs" :key="tab.value">
                    <button type="button" @click="setAppearance(tab.value)"
                            :class="appearance === tab.value ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
                            class="flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors">
                        <span x-html="tab.icon"></span>
                        <span x-text="tab.label"></span>
                    </button>
                </template>
            </div>

            <div class="mt-6">
                <button type="submit"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Save appearance
                </button>
            </div>
        </form>
    </div>
@endpush

@section('content')
    @php
        $breadcrumbs = [
            ['title' => 'Appearance settings', 'href' => route('appearance.edit')],
        ];
    @endphp

    @include('settings.partials.nav')
@endsection

@push('scripts')
<script>
function appearanceTabs(initialAppearance) {
    const sun = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>';
    const moon = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z"/></svg>';
    const monitor = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12A2.25 2.25 0 0118.75 14.25H5.25A2.25 2.25 0 013 12V5.25"/></svg>';
    return {
        tabs: [
            { value: 'light', label: 'Light', icon: sun },
            { value: 'dark', label: 'Dark', icon: moon },
            { value: 'system', label: 'System', icon: monitor },
        ],
        appearance: initialAppearance || localStorage.getItem('appearance') || 'system',
        init() {
            localStorage.setItem('appearance', this.appearance);
            this.apply();
        },
        setAppearance(value) {
            this.appearance = value;
            localStorage.setItem('appearance', value);
            this.apply();
        },
        apply() {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = this.appearance === 'dark' || (this.appearance === 'system' && prefersDark);
            document.documentElement.classList.toggle('dark', isDark);
        },
        syncBeforeSubmit() {
            localStorage.setItem('appearance', this.appearance);
        },
    };
}
</script>
@endpush
