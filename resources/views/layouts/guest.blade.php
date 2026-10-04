<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Welcome') - {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    @include('partials.ui-design-system')
    <style>
        body {
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body class="ui-app-bg h-full antialiased">

<div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
    <div class="w-full max-w-sm">
        <div class="ui-auth-card flex flex-col gap-8">
            <div class="flex flex-col items-center gap-4">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 font-medium">
                    <div class="ui-brand-mark ui-auth-logo">
                        {{ strtoupper(substr(config('app.name'), 0, 2)) }}
                    </div>
                    <span class="sr-only">@yield('card-title')</span>
                </a>

                <div class="space-y-1.5 text-center">
                    <h1 class="text-xl font-semibold tracking-tight text-gray-900">@yield('card-title')</h1>
                    <p class="text-center text-sm text-gray-500">@yield('card-description')</p>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-red-200/80 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">
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
