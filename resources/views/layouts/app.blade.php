<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>

    {{-- Tailwind CDN (v4 play CDN covers all utilities) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        sidebar: {
                            DEFAULT: 'hsl(var(--sidebar-background))',
                            foreground: 'hsl(var(--sidebar-foreground))',
                            accent: 'hsl(var(--sidebar-accent))',
                            'accent-foreground': 'hsl(var(--sidebar-accent-foreground))',
                            border: 'hsl(var(--sidebar-border))',
                        }
                    }
                }
            }
        }
    </script>

    <script>
        (function () {
            const appearance = @json($appearance ?? 'system');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark);
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @stack('head-css')
    <style>
        :root {
            --sidebar-background: 0 0% 98%;
            --sidebar-foreground: 240 5.3% 26.1%;
            --sidebar-accent: 240 4.8% 95.9%;
            --sidebar-accent-foreground: 240 5.9% 10%;
            --sidebar-border: 220 13% 91%;
            --radius: 0.5rem;
        }
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        [x-cloak] { display: none !important; }

        /* Sidebar transition (desktop only — mobile opens/closes instantly) */
        @media (min-width: 1024px) {
            #sidebar { transition: width 0.2s ease, transform 0.2s ease; }
            #main-content.anim-ready { transition: margin-left 0.2s ease; }
        }

        /*
         * Mobile first paint: keep the drawer off-screen before Alpine hydrates.
         * Without this, sidebarOpen starts from the desktop localStorage preference
         * and init() then sets it false — the user sees the drawer hide itself.
         */
        @media (max-width: 1023px) {
            #sidebar:not(.is-open) {
                width: 0 !important;
                transform: translateX(-100%);
                visibility: hidden;
                pointer-events: none;
                border-right-width: 0;
            }
        }

        /* Autocomplete dropdown */
        .combobox-options {
            position: absolute; z-index: 50; width: 100%;
            background: white; border: 1px solid #e5e7eb;
            border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,.1);
            max-height: 240px; overflow-y: auto; margin-top: 4px;
        }
        .combobox-option { padding: 8px 12px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap-8px; }
        .combobox-option:hover, .combobox-option.active { background: #2563eb; color: white; }
        .combobox-option .check { width: 16px; flex-shrink: 0; }

        /* Input focus ring */
        input:focus, select:focus, textarea:focus { outline: 2px solid #3b82f6; outline-offset: 1px; }

        /* Hide number input spin buttons sitewide */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] { -moz-appearance: textfield; }
    </style>
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased"
      x-data="appShell()"
      x-init="init()"
      @keydown.window.escape="closeSidebar()">

{{-- Flash messages --}}
@if(isset($flash) && ($flash['success'] ?? null))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
     class="fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-lg">
    <svg class="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    {{ $flash['success'] }}
    <button @click="show = false" class="ml-2 text-green-600 hover:text-green-800">✕</button>
</div>
@endif
@if(isset($flash) && ($flash['error'] ?? null))
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
     class="fixed top-4 right-4 z-50 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg">
    <svg class="h-4 w-4 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5a1 1 0 012 0v-4a1 1 0 01-2 0v4zm1-8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
    {{ $flash['error'] }}
    <button @click="show = false" class="ml-2 text-red-600 hover:text-red-800">✕</button>
</div>
@endif

@if ($errors->any())
<div x-data="{ show: true }" x-show="show"
     class="fixed top-4 right-4 z-50 max-w-sm rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg">
    <div class="flex items-start gap-2">
        <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5a1 1 0 012 0v-4a1 1 0 01-2 0v4zm1-8a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>
        <div>
            <p class="font-medium">Please fix these errors:</p>
            <ul class="mt-1 list-disc pl-4 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button @click="show = false" class="ml-2 text-red-600 hover:text-red-800">✕</button>
    </div>
</div>
@endif

<div class="flex min-h-screen">
    {{-- Sidebar overlay for mobile --}}
    <div x-show="sidebarOpen && isMobile"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 z-20 bg-black/30 lg:hidden"
         x-cloak></div>

    {{-- SIDEBAR --}}
    <aside id="sidebar"
           :class="sidebarClass()"
           class="fixed left-0 top-0 z-30 flex h-full flex-col border-r border-gray-200 bg-white overflow-hidden">

        {{-- Sidebar header --}}
        <div class="flex h-14 items-center border-b border-gray-100 px-3 flex-shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-blue-700 text-white font-bold text-sm">
                    {{ strtoupper(substr(config('app.name'), 0, 2)) }}
                </div>
                <span x-show="sidebarOpen" x-cloak class="font-semibold text-sm text-gray-900 truncate">
                    {{ config('app.name') }}
                </span>
            </a>
            <button @click="sidebarOpen = !sidebarOpen" x-show="sidebarOpen"
                    class="ml-auto flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
            </button>
        </div>

        {{-- Menu filter --}}
        <div x-show="sidebarOpen" x-cloak class="flex-shrink-0 border-b border-gray-100 px-2 py-2">
            <label for="sidebar-menu-search" class="sr-only">Filter menu</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input id="sidebar-menu-search"
                       type="search"
                       x-model="menuSearch"
                       placeholder="Filter menu…"
                       autocomplete="off"
                       data-testid="sidebar-menu-search"
                       class="w-full rounded-md border border-gray-300 py-1.5 pl-8 pr-8 text-sm text-gray-700 placeholder:text-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                <button type="button"
                        x-show="menuSearch.trim()"
                        @click="menuSearch = ''"
                        class="absolute right-1.5 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                        aria-label="Clear menu filter">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-2 px-2"
             @click="if (isMobile && $event.target.closest('a[href]')) sidebarOpen = false">
            @include('partials.sidebar-nav')
        </nav>

        {{-- Sidebar footer: user menu --}}
        <div class="border-t border-gray-100 p-2 flex-shrink-0">
            @if(isset($_sidebar))
            @php $user = $_sidebar['user'] ?? null; @endphp
            @if($user)
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                        class="flex w-full items-center gap-2 rounded-lg p-2 text-sm hover:bg-gray-100 text-left">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 font-semibold text-xs">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div x-show="sidebarOpen" x-cloak class="flex-1 min-w-0">
                        <div class="truncate font-medium text-gray-900 text-xs">{{ $user->name }}</div>
                        <div class="truncate text-gray-500 text-xs">{{ $user->email }}</div>
                    </div>
                    <svg x-show="sidebarOpen" x-cloak class="h-4 w-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/></svg>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                     class="absolute bottom-full left-0 mb-1 w-52 rounded-lg border border-gray-200 bg-white shadow-lg py-1 z-50">
                    <div class="px-3 py-2 border-b border-gray-100">
                        <div class="font-medium text-sm text-gray-900">{{ $user->name }}</div>
                        <div class="text-xs text-gray-500">{{ $user->email }}</div>
                    </div>
                    <a href="{{ route('transaction-defaults.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Transaction defaults
                    </a>
                    <div class="border-t border-gray-100 my-1"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
            @endif
            @endif
        </div>
    </aside>

    {{-- Main content --}}
    <div id="main-content"
         x-init="$nextTick(() => $el.classList.add('anim-ready'))"
         :class="sidebarOpen ? (isMobile ? 'ml-0' : 'ml-64') : (isMobile ? 'ml-0' : 'ml-14')"
         class="flex flex-1 flex-col min-w-0 h-screen overflow-hidden">

        {{-- Top header --}}
        <header class="sticky top-0 z-10 flex h-14 items-center gap-3 border-b border-gray-200 bg-white px-4">
            {{-- Mobile menu toggle --}}
            <button @click="sidebarOpen = !sidebarOpen"
                    class="flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 lg:hidden">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            {{-- Desktop sidebar expand (when collapsed) --}}
            <button @click="sidebarOpen = !sidebarOpen" x-show="!sidebarOpen && !isMobile"
                    class="hidden lg:flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
            </button>

            {{-- Breadcrumbs --}}
            @if(isset($breadcrumbs) && count($breadcrumbs) > 0)
            <nav class="flex items-center gap-1 text-sm text-gray-500 min-w-0">
                @foreach($breadcrumbs as $i => $crumb)
                    @if($i > 0)<svg class="h-3.5 w-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>@endif
                    @if($i < count($breadcrumbs) - 1)
                        <a href="{{ $crumb['href'] }}" class="hover:text-gray-900 hover:underline truncate">{{ $crumb['title'] }}</a>
                    @else
                        <span class="font-medium text-gray-900 truncate">{{ $crumb['title'] }}</span>
                    @endif
                @endforeach
            </nav>
            @endif

            <div class="ml-auto flex items-center gap-2">
                @if($hasStaffChecklist ?? false)
                <a href="{{ route('my-checklist.index') }}"
                   class="relative flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100"
                   title="Checklist peran"
                   data-testid="header-checklist-link">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    @if(($staffChecklistPendingCount ?? 0) > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-bold text-white">
                        {{ $staffChecklistPendingCount > 99 ? '99+' : $staffChecklistPendingCount }}
                    </span>
                    @endif
                </a>
                @endif
                @if(($stockNotificationUnreadCount ?? 0) > 0 || auth()->user()?->can(\App\Models\ItemStockNotification::getPermissions()['view']))
                <a href="{{ route('stock-notifications.index') }}"
                   class="relative flex h-8 w-8 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100"
                   title="Stock alerts">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    @if(($stockNotificationUnreadCount ?? 0) > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                        {{ $stockNotificationUnreadCount > 99 ? '99+' : $stockNotificationUnreadCount }}
                    </span>
                    @endif
                </a>
                @endif
            </div>
        </header>

        {{-- Page content (scrollable; header stays pinned above) --}}
        <main id="app-main-scroll" class="flex-1 overflow-y-auto overflow-x-hidden">
            @yield('content')
        </main>
    </div>
</div>

{{-- Alpine.js CDN --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

<script>
function formatAmountId(value) {
    return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function formatNumberId(value) {
    return Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function appShell() {
    const isMobileViewport = () => window.innerWidth < 1024;
    const savedDesktopOpen = () => localStorage.getItem('sidebarOpen') !== 'false';

    return {
        // Mobile always starts closed so Alpine never paints open-then-hide.
        // Desktop honors the saved preference; do not seed from localStorage on mobile
        // or a prior desktop session would flash the drawer on every phone page load.
        isMobile: isMobileViewport(),
        sidebarOpen: isMobileViewport() ? false : savedDesktopOpen(),
        menuSearch: '',
        sidebarClass() {
            if (this.sidebarOpen) {
                return 'w-64 is-open';
            }

            return this.isMobile ? 'w-0 -translate-x-full' : 'w-14';
        },
        persistSidebarOpen() {
            if (!this.isMobile) {
                localStorage.setItem('sidebarOpen', this.sidebarOpen);
            }
        },
        matchesNav(...labels) {
            const q = this.menuSearch.trim().toLowerCase();
            if (!q) {
                return true;
            }

            return labels.some((label) => String(label).toLowerCase().includes(q));
        },
        navLinkVisible(label, groupLabel = '') {
            if (!this.menuSearch.trim()) {
                return true;
            }

            return this.matchesNav(label, groupLabel);
        },
        navGroupVisible(...labels) {
            return this.matchesNav(...labels);
        },
        syncNavGroupOpen(openRef, defaultOpen, ...labels) {
            if (this.menuSearch.trim() && this.matchesNav(...labels)) {
                openRef.open = true;

                return;
            }

            if (!this.menuSearch.trim()) {
                openRef.open = defaultOpen;
            }
        },
        init() {
            this.isMobile = isMobileViewport();
            if (this.isMobile) {
                this.sidebarOpen = false;
            }
            window.addEventListener('resize', () => {
                const nowMobile = isMobileViewport();
                if (nowMobile === this.isMobile) {
                    return;
                }
                this.isMobile = nowMobile;
                this.sidebarOpen = nowMobile ? false : savedDesktopOpen();
            });
            this.$watch('sidebarOpen', () => this.persistSidebarOpen());
            this.$nextTick(() => {
                const main = document.getElementById('app-main-scroll');
                if (main) main.scrollTop = 0;
            });
        },
        closeSidebar() { if (this.isMobile) this.sidebarOpen = false; }
    };
}

// ─── Combobox keyboard helpers (mobile + external keyboard friendly) ───────
function isMobileComboboxContext() {
    if (window._isMobileCombobox != null) return window._isMobileCombobox;
    const coarse = window.matchMedia('(pointer: coarse)').matches;
    const ua = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent);
    window._isMobileCombobox = coarse || ua;
    return window._isMobileCombobox;
}

// Resolve a navigation key across engines. Android/IME keyboards frequently send
// key "Unidentified" with keyCode 229, so `code` and the legacy keyCode are both
// consulted before falling back to `key`.
function normalizeNavigationKey(e) {
    const codeMap = { NumpadEnter: 'Enter' };
    if (e.code) {
        if (e.code.startsWith('Arrow') || ['Enter', 'NumpadEnter', 'Escape', 'Tab', 'Backspace', 'Delete'].includes(e.code)) {
            return codeMap[e.code] || e.code;
        }
    }

    const legacy = { 13: 'Enter', 27: 'Escape', 9: 'Tab', 38: 'ArrowUp', 40: 'ArrowDown', 37: 'ArrowLeft', 39: 'ArrowRight', 8: 'Backspace', 46: 'Delete' };
    const kc = e.keyCode || e.which;
    if (kc && legacy[kc]) return legacy[kc];

    const key = e.key;
    // 229 is the IME "processing" placeholder; there is no usable key here.
    if (!key || key === 'Unidentified' || key === 'Process') return '';
    const short = { Down: 'ArrowDown', Up: 'ArrowUp', Esc: 'Escape', Left: 'ArrowLeft', Right: 'ArrowRight' };
    return short[key] || key;
}

function isPrintableComboboxKey(key, e) {
    return key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey;
}

// Swallow Enter/Tab field navigation briefly after programmatic focus (Android IME).
function suppressFieldNavigation(ms = 400) {
    window._suppressFieldNavUntil = Date.now() + ms;
}

function isFieldNavigationSuppressed() {
    return Date.now() < (window._suppressFieldNavUntil || 0);
}

// Defer focus until after keyup: $nextTick runs as a microtask before keyup on
// Android/external keyboards, so the same Enter's keyup lands on the next field.
function deferFocusElement(id, select = true) {
    setTimeout(() => {
        const el = document.getElementById(id);
        if (el) {
            el.focus();
            if (select && typeof el.select === 'function') el.select();
        }
    }, 0);
}

// ─── Filter form Enter → next field (selects included) ───────────────────────
function filterFormFocusables(form) {
    const nodes = form.querySelectorAll(
        'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([disabled]),' +
        'select:not([disabled]),' +
        'textarea:not([disabled]),' +
        'button[type="submit"]:not([disabled])'
    );

    return Array.from(nodes).filter((el) => {
        if (!(el instanceof HTMLElement)) {
            return false;
        }
        if (el.offsetParent === null) {
            return false;
        }
        if (el.closest('[x-cloak]')) {
            return false;
        }
        const style = window.getComputedStyle(el);
        return style.visibility !== 'hidden' && style.display !== 'none';
    });
}

function focusNextInFilterForm(form, current) {
    const focusables = filterFormFocusables(form);
    const idx = focusables.indexOf(current);
    if (idx === -1 || idx >= focusables.length - 1) {
        return false;
    }

    const next = focusables[idx + 1];
    next.focus();
    if (next instanceof HTMLInputElement && typeof next.select === 'function') {
        next.select();
    }
    suppressFieldNavigation(400);
    return true;
}

function submitFilterForm(form) {
    const submit = form.querySelector('button[type="submit"]:not([disabled])');
    if (!submit) {
        return false;
    }

    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit(submit);
    } else {
        submit.click();
    }

    suppressFieldNavigation(400);
    return true;
}

let _filterEnterHandled = false;

function processFilterEnterNav(e) {
    if (normalizeNavigationKey(e) !== 'Enter' || e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) {
        return false;
    }
    if (isFieldNavigationSuppressed()) {
        return false;
    }

    const el = e.target;
    if (!(el instanceof HTMLElement)) {
        return false;
    }

    const form = el.closest('form.filter-enter-nav');
    if (!form) {
        return false;
    }

    if (el.tagName === 'TEXTAREA' || el.tagName === 'BUTTON') {
        return false;
    }

    if (el.matches('input[type="checkbox"], input[type="radio"]')) {
        return false;
    }

    // Async combobox inputs handle Enter when their dropdown is open.
    if (el.closest('[x-data*="asyncCombobox"]') && el.matches('input')) {
        return false;
    }

    if (el.matches('[data-filter-enter-submit]') && el.matches('input, select')) {
        return submitFilterForm(form);
    }

    if (el.matches('input, select')) {
        return focusNextInFilterForm(form, el);
    }

    return false;
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[method="GET"]:not([data-no-filter-nav])').forEach(function (form) {
        form.classList.add('filter-enter-nav');
    });
});

document.addEventListener('keydown', function (e) {
    _filterEnterHandled = false;
    if (processFilterEnterNav(e)) {
        _filterEnterHandled = true;
        e.preventDefault();
    }
}, true);

document.addEventListener('keyup', function (e) {
    if (_filterEnterHandled) {
        _filterEnterHandled = false;
        return;
    }
    if (processFilterEnterNav(e)) {
        e.preventDefault();
    }
}, true);

// ─── Number inputs: block wheel + arrow-key value changes ───────────────────
(function () {
    function activeNumberInput() {
        const el = document.activeElement;
        return el instanceof HTMLInputElement
            && el.type === 'number'
            && !el.disabled
            && !el.readOnly;
    }

    document.addEventListener('wheel', function (e) {
        if (activeNumberInput()) {
            e.preventDefault();
        }
    }, { passive: false });

    document.addEventListener('keydown', function (e) {
        if (!activeNumberInput()) {
            return;
        }
        const key = normalizeNavigationKey(e);
        if (key === 'ArrowUp' || key === 'ArrowDown') {
            e.preventDefault();
        }
    }, true);
})();

// ─── Autocomplete defaults (addrbook + item comboboxes) ─────────────────────
const COMBOBOX_MIN_CHARS = 3;
const COMBOBOX_MAX_RESULTS = 8;

function comboboxSearchable(q) {
    return String(q || '').trim().length >= COMBOBOX_MIN_CHARS;
}

// ─── Submit guard (prevents double-click / double-submit) ───────────────────
function submitGuardFields() {
    return {
        submitting: false,
        _submitLocked: false,
        _submitIdempotencyKey: null,
    };
}

function beginSubmit(ctx) {
    if (ctx._submitLocked) {
        return false;
    }
    ctx._submitLocked = true;
    ctx.submitting = true;
    ctx._submitIdempotencyKey = (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : (Date.now().toString(36) + Math.random().toString(36).slice(2));
    return true;
}

function endSubmit(ctx) {
    ctx._submitLocked = false;
    ctx.submitting = false;
    ctx._submitIdempotencyKey = null;
}

function idempotencyHeaders(ctx) {
    if (!ctx._submitIdempotencyKey) {
        return {};
    }
    return { 'X-Idempotency-Key': ctx._submitIdempotencyKey };
}

function formSubmitGuard() {
    return {
        ...submitGuardFields(),
        guardFormSubmit(event) {
            if (!beginSubmit(this)) {
                event.preventDefault();
                return;
            }
            const form = event.target;
            if (form instanceof HTMLFormElement) {
                let input = form.querySelector('input[name="_idempotency_key"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = '_idempotency_key';
                    form.appendChild(input);
                }
                input.value = this._submitIdempotencyKey;
                window.markFormSubmitInFlight?.(form);
            }
        },
    };
}

(function () {
    const inFlightForms = new WeakSet();

    window.markFormSubmitInFlight = function (form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        inFlightForms.add(form);
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            btn.disabled = true;
        });
    };

    window.releaseFormSubmitGuard = function (form) {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        inFlightForms.delete(form);
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            btn.disabled = false;
        });
    };

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        if (form.hasAttribute('data-skip-submit-guard')) {
            return;
        }
        if (inFlightForms.has(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);
})();

// ─── Reusable Alpine async-combobox component ─────────────────────────────
function asyncCombobox(config) {
    return {
        query: '',
        items: [],
        loading: false,
        open: false,
        selected: config.initial || null,
        activeIndex: -1,
        _keydownHandled: false,
        _outsideClick: null,
        debounceTimer: null,
        endpoint: config.endpoint,
        queryParam: config.queryParam || 'search',
        additionalParams: config.additionalParams || {},
        placeholder: config.placeholder || 'Search...',
        hiddenField: config.hiddenField || null,
        onSelect: config.onSelect || null,
        excludedIds: config.excludedIds || [],
        minChars: config.minChars ?? COMBOBOX_MIN_CHARS,
        maxResults: config.maxResults ?? COMBOBOX_MAX_RESULTS,

        init() {
            if (this.selected) {
                this.query = this.selected.name || '';
                if (this.onSelect) {
                    this.onSelect(this.selected);
                }
            }

            this.$watch('open', (isOpen) => {
                if (isOpen) {
                    // Defer so the click that opened the panel does not count as "outside".
                    setTimeout(() => {
                        if (!this.open) return;
                        this._attachOutsideClick();
                    }, 0);
                } else {
                    this._detachOutsideClick();
                }
            });
        },

        destroy() {
            this._detachOutsideClick();
        },

        _attachOutsideClick() {
            if (this._outsideClick) return;
            this._outsideClick = (e) => {
                if (!this.$el.contains(e.target)) {
                    this.open = false;
                }
            };
            document.addEventListener('click', this._outsideClick, true);
        },

        _detachOutsideClick() {
            if (!this._outsideClick) return;
            document.removeEventListener('click', this._outsideClick, true);
            this._outsideClick = null;
        },

        needsMoreChars() {
            return String(this.query || '').trim().length < this.minChars;
        },

        emptyMessage() {
            if (this.needsMoreChars()) {
                return `Type at least ${this.minChars} characters to search.`;
            }
            if (this.loading) return 'Searching…';
            return 'Nothing found.';
        },

        doSearch(q) {
            clearTimeout(this.debounceTimer);
            const term = String(q || '').trim();
            if (term.length < this.minChars) {
                this.items = [];
                this.loading = false;
                this.activeIndex = -1;
                return;
            }
            this.loading = true;
            this.debounceTimer = setTimeout(async () => {
                try {
                    const params = new URLSearchParams({ [this.queryParam]: term, json: true, ...this.additionalParams });
                    const sep = this.endpoint.includes('?') ? '&' : '?';
                    const res = await fetch(`${this.endpoint}${sep}${params}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    let all = Array.isArray(data) ? data : (data.data || []);
                    if (this.excludedIds.length) all = all.filter(i => !this.excludedIds.includes(String(i.id)));
                    this.items = all.slice(0, this.maxResults);
                    this.activeIndex = -1;
                } catch(e) { this.items = []; }
                finally { this.loading = false; }
            }, 300);
        },

        selectItem(item) {
            this.selected = item;
            this.query = item ? (item.name || '') : '';
            this.open = false;
            this.activeIndex = -1;
            if (this.hiddenField) {
                const el = document.getElementById(this.hiddenField);
                if (el) el.value = item ? item.id : '';
            }
            if (this.onSelect) this.onSelect(item);
            if (item) suppressFieldNavigation(400);
        },

        clearSelection() {
            this.selectItem(null);
            this.query = '';
            this.items = [];
        },

        handleInput() {
            this.open = true;
            this.doSearch(this.query);
        },

        handleFocus() {
            this.activeIndex = -1;
            if (this.needsMoreChars() && this.items.length === 0) {
                return;
            }
            this.open = true;
            if (this.items.length === 0 && comboboxSearchable(this.query)) {
                this.doSearch(this.query);
            }
        },

        keyboardNavLock() {
            // Only lock once the user is arrow-navigating results. Locking while
            // activeIndex is still -1 blocks the mobile soft keyboard when correcting
            // a partially typed search term.
            return isMobileComboboxContext() && this.open && this.items.length > 0 && this.activeIndex >= 0;
        },

        handlePointerDown() {
            if (isMobileComboboxContext()) {
                this.activeIndex = -1;
            }
        },

        handleKeydown(e) {
            this._keydownHandled = false;
            if (this._processKey(e)) {
                this._keydownHandled = true;
                e.preventDefault();
            }
        },

        // Fallback for keyboards whose keydown carries no usable key (Android/IME).
        handleKeyup(e) {
            if (this._keydownHandled) {
                this._keydownHandled = false;
                return;
            }
            const key = normalizeNavigationKey(e);
            if (['ArrowDown', 'ArrowUp', 'Enter'].includes(key) && this._processKey(e)) {
                e.preventDefault();
            }
        },

        _processKey(e) {
            const key = normalizeNavigationKey(e);
            if (!key) return false;
            const len = this.items.length;

            if (this.keyboardNavLock()) {
                if (key === 'Backspace') {
                    this.query = this.query.slice(0, -1);
                    this.handleInput();
                    return true;
                }
                if (key === 'Delete') {
                    this.query = '';
                    this.handleInput();
                    return true;
                }
                if (isPrintableComboboxKey(key, e)) {
                    this.query += key;
                    this.activeIndex = -1;
                    this.handleInput();
                    return true;
                }
            }

            if (key === 'ArrowDown') {
                if (!this.open) {
                    this.open = true;
                    if (len === 0 && comboboxSearchable(this.query)) { this.doSearch(this.query); return true; }
                }
                if (len === 0) return true;
                this.activeIndex = this.activeIndex < len - 1 ? this.activeIndex + 1 : 0;
                this.scrollActive();
                return true;
            }
            if (key === 'ArrowUp') {
                if (!this.open) {
                    this.open = true;
                    if (len === 0 && comboboxSearchable(this.query)) { this.doSearch(this.query); return true; }
                }
                if (len === 0) return true;
                this.activeIndex = this.activeIndex > 0 ? this.activeIndex - 1 : len - 1;
                this.scrollActive();
                return true;
            }
            if (key === 'Enter') {
                const filterForm = this.$el.closest('form.filter-enter-nav');
                if (filterForm && !this.open) {
                    const input = this.$el.querySelector('input[type="text"], input:not([type="hidden"])');
                    if (input && focusNextInFilterForm(filterForm, input)) {
                        return true;
                    }
                }
                if (!this.open) {
                    this.open = true;
                    if (len === 0 && comboboxSearchable(this.query)) this.doSearch(this.query);
                    return true;
                }
                if (this.activeIndex >= 0 && this.items[this.activeIndex]) {
                    this.selectItem(this.items[this.activeIndex]);
                }
                return true;
            }
            if (key === 'Escape') {
                this.open = false;
                this.activeIndex = -1;
                return true;
            }
            if (key === 'Tab') {
                if (this.open && this.activeIndex >= 0 && this.items[this.activeIndex]) {
                    this.selectItem(this.items[this.activeIndex]);
                }
                this.open = false;
                return false;
            }
            return false;
        },

        scrollActive() {
            this.$nextTick(() => {
                const list = this.$refs.optionsList;
                if (!list) return;
                // Only the rendered option rows carry .combobox-option; the empty-state
                // div and the x-for <template> node are skipped, so indexing stays aligned.
                const el = list.querySelectorAll('.combobox-option')[this.activeIndex];
                if (el) el.scrollIntoView({ block: 'nearest' });
            });
        },

        get displayValue() {
            return this.selected ? (this.selected.name || '') : '';
        }
    };
}
</script>
@stack('scripts')
</body>
</html>
