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

        /* Sidebar transition */
        #sidebar { transition: width 0.2s ease, transform 0.2s ease; }
        #main-content { transition: margin-left 0.2s ease; }

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
    </style>
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased"
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

<div class="flex h-full">
    {{-- Sidebar overlay for mobile --}}
    <div x-show="sidebarOpen && isMobile"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-20 bg-black/30"
         x-cloak></div>

    {{-- SIDEBAR --}}
    <aside id="sidebar"
           :class="sidebarOpen ? 'w-64' : (isMobile ? 'w-0 -translate-x-full' : 'w-14')"
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

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-2 px-2">
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
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Profile
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
         :class="sidebarOpen ? (isMobile ? 'ml-0' : 'ml-64') : (isMobile ? 'ml-0' : 'ml-14')"
         class="flex flex-1 flex-col min-h-full min-w-0">

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

            <div class="ml-auto"></div>
        </header>

        {{-- Page content --}}
        <main class="flex-1">
            @yield('content')
        </main>
    </div>
</div>

{{-- Alpine.js CDN --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
function appShell() {
    return {
        sidebarOpen: localStorage.getItem('sidebarOpen') !== 'false',
        isMobile: window.innerWidth < 1024,
        init() {
            window.addEventListener('resize', () => {
                this.isMobile = window.innerWidth < 1024;
                if (this.isMobile) this.sidebarOpen = false;
            });
            this.$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val));
        },
        closeSidebar() { if (this.isMobile) this.sidebarOpen = false; }
    };
}

// ─── Reusable Alpine async-combobox component ─────────────────────────────
function asyncCombobox(config) {
    return {
        query: '',
        items: [],
        loading: false,
        open: false,
        selected: config.initial || null,
        activeIndex: -1,
        debounceTimer: null,
        endpoint: config.endpoint,
        queryParam: config.queryParam || 'search',
        additionalParams: config.additionalParams || {},
        placeholder: config.placeholder || 'Search...',
        hiddenField: config.hiddenField || null,
        onSelect: config.onSelect || null,
        excludedIds: config.excludedIds || [],

        init() {
            // Pre-load options
            this.doSearch('');
        },

        doSearch(q) {
            clearTimeout(this.debounceTimer);
            this.loading = true;
            this.debounceTimer = setTimeout(async () => {
                try {
                    const params = new URLSearchParams({ [this.queryParam]: q, json: true, ...this.additionalParams });
                    const sep = this.endpoint.includes('?') ? '&' : '?';
                    const res = await fetch(`${this.endpoint}${sep}${params}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    let all = Array.isArray(data) ? data : (data.data || []);
                    if (this.excludedIds.length) all = all.filter(i => !this.excludedIds.includes(String(i.id)));
                    this.items = all;
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
        },

        clearSelection() {
            this.selectItem(null);
            this.query = '';
            this.doSearch('');
        },

        handleInput() {
            this.open = true;
            this.doSearch(this.query);
        },

        handleFocus() {
            this.open = true;
            if (this.items.length === 0) this.doSearch(this.query);
        },

        handleKeydown(e) {
            const len = this.items.length;
            if (!this.open && (e.key === 'ArrowDown' || e.key === 'Enter')) {
                e.preventDefault();
                this.open = true;
                if (len === 0) this.doSearch(this.query);
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.activeIndex = (this.activeIndex + 1) % Math.max(len, 1);
                this.scrollActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.activeIndex = this.activeIndex <= 0 ? len - 1 : this.activeIndex - 1;
                this.scrollActive();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (this.open && this.activeIndex >= 0 && this.items[this.activeIndex]) {
                    this.selectItem(this.items[this.activeIndex]);
                }
            } else if (e.key === 'Escape') {
                this.open = false;
                this.activeIndex = -1;
            } else if (e.key === 'Tab') {
                if (this.open && this.activeIndex >= 0 && this.items[this.activeIndex]) {
                    this.selectItem(this.items[this.activeIndex]);
                }
                this.open = false;
            }
        },

        scrollActive() {
            this.$nextTick(() => {
                const list = this.$refs.optionsList;
                if (list) {
                    const item = list.children[this.activeIndex];
                    if (item) item.scrollIntoView({ block: 'nearest' });
                }
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
