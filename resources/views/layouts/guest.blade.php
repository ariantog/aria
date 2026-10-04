<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') - {{ config('app.name') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        [x-cloak] { display: none !important; }
        input:focus, select:focus, textarea:focus { outline: 2px solid #3b82f6; outline-offset: 1px; }
    </style>
    @stack('head')
</head>
<body class="h-full bg-white text-gray-900 antialiased">

<div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-gray-50 p-6 md:p-10">
    <div class="w-full max-w-sm">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col items-center gap-4">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium">
                    <div class="mb-1 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-700 text-white font-bold">
                        {{ strtoupper(substr(config('app.name'), 0, 2)) }}
                    </div>
                    <span class="sr-only">@yield('card-title')</span>
                </a>

                <div class="space-y-2 text-center">
                    <h1 class="text-xl font-medium">@yield('card-title')</h1>
                    <p class="text-center text-sm text-gray-500">@yield('card-description')</p>
                </div>
            </div>

            {{-- Validation errors --}}
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-0.5 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('card')
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@stack('scripts')
</body>
</html>
