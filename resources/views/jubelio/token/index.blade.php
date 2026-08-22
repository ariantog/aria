@extends('layouts.app')

@section('title', 'Jubelio Koneksi')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Jubelio', 'href' => route('jubelio.index')],
    ['title' => 'Koneksi', 'href' => route('jubelio.token.index')],
];
$healthy = $status['has_token'] && ! $status['is_expired'] && ($status['last_api_check_ok'] ?? null) !== false;
@endphp

<div class="flex flex-col gap-6 p-4">
    <div>
        <h1 class="text-2xl font-bold">Jubelio Koneksi</h1>
        <p class="mt-1 text-sm text-gray-500">
            Status token API Jubelio. Token di-refresh otomatis setiap {{ (int) config('services.jubelio.token_ttl_hours', 10) }} jam,
            saat ditolak API (401), atau manual dari halaman ini.
        </p>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Status Token</p>
            <p class="mt-2 text-lg font-semibold {{ $status['has_token'] && ! $status['is_expired'] ? 'text-green-700' : 'text-red-700' }}">
                @if(! $status['has_token'])
                Belum ada token
                @elseif($status['is_expired'])
                Kedaluwarsa
                @else
                Aktif
                @endif
            </p>
            @if($status['token_preview'])
            <p class="mt-1 font-mono text-xs text-gray-500">{{ $status['token_preview'] }}</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Kadaluarsa</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">
                @if($status['expires_at'])
                {{ \Carbon\Carbon::parse($status['expires_at'])->translatedFormat('d M Y H:i') }}
                @else
                —
                @endif
            </p>
            @if($status['expires_in_minutes'] !== null && ! $status['is_expired'])
            <p class="mt-1 text-xs text-gray-500">{{ $status['expires_in_minutes'] }} menit lagi</p>
            @endif
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-medium uppercase text-gray-400">Cek API Terakhir</p>
            <p class="mt-2 text-lg font-semibold {{ ($status['last_api_check_ok'] ?? null) === true ? 'text-green-700' : (($status['last_api_check_ok'] ?? null) === false ? 'text-red-700' : 'text-gray-700') }}">
                @if(($status['last_api_check_ok'] ?? null) === true)
                OK
                @elseif(($status['last_api_check_ok'] ?? null) === false)
                Gagal
                @else
                Belum dicek
                @endif
            </p>
            @if($status['last_api_check_at'])
            <p class="mt-1 text-xs text-gray-500">{{ \Carbon\Carbon::parse($status['last_api_check_at'])->diffForHumans() }}</p>
            @endif
        </div>
    </div>

    @if(($status['consecutive_failures'] ?? 0) > 0)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-medium">Ada {{ $status['consecutive_failures'] }} kegagalan berturut-turut.</p>
        <p class="mt-1">Jika refresh token berulang kali gagal, kemungkinan masalah koneksi, kredensial (.env), atau layanan Jubelio sedang down.</p>
    </div>
    @endif

    <div class="max-w-3xl rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-sm font-semibold text-gray-900">Detail</h2>
        </div>
        <dl class="divide-y divide-gray-100 px-6 text-sm">
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">JUBELIO_ACTIVE</dt>
                <dd class="sm:col-span-2 {{ $status['jubelio_active'] ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $status['jubelio_active'] ? 'true' : 'false' }}
                </dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">Kredensial dikonfigurasi</dt>
                <dd class="sm:col-span-2">{{ $status['configured'] ? 'Ya' : 'Tidak — periksa JUBELIO_URL / EMAIL / PASSWORD' }}</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">Refresh terakhir</dt>
                <dd class="sm:col-span-2">{{ $status['last_refreshed_at'] ? \Carbon\Carbon::parse($status['last_refreshed_at'])->translatedFormat('d M Y H:i:s') : '—' }}</dd>
            </div>
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">Login sukses terakhir</dt>
                <dd class="sm:col-span-2">{{ $status['last_auth_success_at'] ? \Carbon\Carbon::parse($status['last_auth_success_at'])->translatedFormat('d M Y H:i:s') : '—' }}</dd>
            </div>
            @if($status['last_auth_error'])
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">Error login terakhir</dt>
                <dd class="sm:col-span-2 font-mono text-xs text-red-700">{{ $status['last_auth_error'] }}</dd>
            </div>
            @endif
            @if($status['last_api_error'])
            <div class="grid gap-1 py-3 sm:grid-cols-3">
                <dt class="text-gray-500">Error API terakhir</dt>
                <dd class="sm:col-span-2 font-mono text-xs text-red-700">{{ $status['last_api_error'] }}</dd>
            </div>
            @endif
        </dl>
        <div class="flex flex-wrap gap-3 border-t border-gray-100 px-6 py-4">
            <form method="POST" action="{{ route('jubelio.token.refresh') }}">
                @csrf
                <button type="submit"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800"
                        data-testid="jubelio-token-refresh">
                    Refresh Token
                </button>
            </form>
            <form method="POST" action="{{ route('jubelio.token.check') }}">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        data-testid="jubelio-token-check">
                    Cek Koneksi (login + API)
                </button>
            </form>
        </div>
    </div>

    <p class="text-xs text-gray-400">
        Cron <code>jubelio:check-connection</code> berjalan setiap jam untuk memverifikasi koneksi secara otomatis.
        Semua panggilan API juga akan otomatis refresh token jika Jubelio mengembalikan HTTP 401/403.
    </p>
</div>
@endsection
