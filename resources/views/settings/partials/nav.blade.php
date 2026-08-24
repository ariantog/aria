@php
    $settingsNav = [
        ['title' => 'Profile', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.edit')],
        ['title' => 'Password', 'href' => route('user-password.edit'), 'active' => request()->routeIs('user-password.edit')],
        ['title' => 'Two-Factor Auth', 'href' => route('two-factor.show'), 'active' => request()->routeIs('two-factor.show')],
        ['title' => 'Transaction defaults', 'href' => route('transaction-defaults.edit'), 'active' => request()->routeIs('transaction-defaults.*')],
        ['title' => 'Appearance', 'href' => route('appearance.edit'), 'active' => request()->routeIs('appearance.*')],
    ];
@endphp

<div class="px-4 py-6">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Settings</h1>
        <p class="text-sm text-gray-500">Manage your profile and account settings</p>
    </div>

    <div class="flex flex-col lg:flex-row lg:space-x-12">
        <aside class="w-full max-w-xl lg:w-48">
            <nav class="flex flex-col space-y-1" aria-label="Settings">
                @foreach ($settingsNav as $item)
                    <a href="{{ $item['href'] }}"
                       class="w-full rounded-md px-3 py-2 text-sm font-medium {{ $item['active'] ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-50' }}">
                        {{ $item['title'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="my-6 border-t border-gray-200 lg:hidden"></div>

        <div class="flex-1 md:max-w-2xl">
            <section class="max-w-xl space-y-12">
                {{ $slot ?? '' }}
                @yield('settings-content')
            </section>
        </div>
    </div>
</div>
