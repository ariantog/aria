<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Suspended - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }</style>
</head>
<body class="h-full">
<div class="flex min-h-screen flex-col items-center justify-center bg-zinc-950 p-4 text-zinc-100">
    <div class="w-full max-w-md space-y-8 text-center">
        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-900/20">
            <svg class="h-12 w-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M11.48 3.499l-8.66 15A1.5 1.5 0 004.12 21h15.76a1.5 1.5 0 001.3-2.5l-8.66-15a1.5 1.5 0 00-2.6 0z"/>
            </svg>
        </div>

        <div class="space-y-4">
            <h1 class="text-3xl font-bold tracking-tighter text-white sm:text-4xl">Account Suspended</h1>
            <p class="text-lg text-zinc-400">
                Your account has been deactivated by an administrator.
                You strictly cannot access this application.
            </p>
        </div>

        <div class="rounded-lg border border-zinc-800 bg-zinc-900/50 p-6 text-sm text-zinc-400">
            <p>If you believe this is a mistake, please contact support immediately with your account details.</p>
            <div class="mt-4 border-t border-zinc-800 pt-4">
                <p class="font-mono text-zinc-300">support@active-aria.test</p>
            </div>
        </div>

        <div class="flex justify-center">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md border border-zinc-700 px-4 py-2 text-sm font-medium hover:bg-zinc-800 hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                    </svg>
                    Sign Out
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
