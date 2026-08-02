<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Welcome - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif; }</style>
</head>
<body class="h-full">
<div class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8">
    <header class="mb-6 w-full max-w-[335px] text-sm lg:max-w-4xl">
        <nav class="flex items-center justify-end gap-4">
            @auth
                <a href="{{ route('dashboard') }}"
                   class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a]">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035]">
                    Log in
                </a>
                @if ($canRegister ?? false)
                    <a href="{{ route('register') }}"
                       class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a]">
                        Register
                    </a>
                @endif
            @endauth
        </nav>
    </header>

    <div class="flex w-full items-center justify-center lg:grow">
        <main class="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
            <div class="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20">
                <h1 class="mb-1 font-medium text-xl">{{ config('app.name') }}</h1>
                <p class="mb-4 text-[#706f6c]">
                    Welcome. Sign in to manage your inventory, transactions, and reports.
                </p>
                <ul class="flex gap-3 text-sm leading-normal">
                    <li>
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:bg-black">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="inline-block rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:bg-black">
                                Log in
                            </a>
                        @endauth
                    </li>
                </ul>
            </div>
            <div class="relative -mb-px aspect-[335/376] w-full shrink-0 overflow-hidden rounded-t-lg bg-[#fff2f2] lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg flex items-center justify-center">
                <span class="text-6xl font-bold text-[#F53003]">{{ strtoupper(substr(config('app.name'), 0, 2)) }}</span>
            </div>
        </main>
    </div>
</div>
</body>
</html>
