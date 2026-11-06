@extends('layouts.app')

@section('title', 'TikTok Shop Chat')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'TikTok Shop', 'href' => route('tiktok-shop.chats.index')],
    ['title' => 'Customer Chat', 'href' => route('tiktok-shop.chats.index')],
];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="tiktok-shop-chats-page">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-900">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-900 text-xs font-bold text-white">TT</span>
                TikTok Shop — Customer Chat
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">
                Mockup UI — data demo disimpan di cache aplikasi (bukan tabel DB). Saat partner TikTok disetujui, ganti
                <code class="rounded bg-gray-100 px-1 text-xs">TIKTOK_SHOP_DRIVER=live</code> dan isi kredensial Open Platform.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $isMock ? 'bg-amber-100 text-amber-900' : 'bg-green-100 text-green-900' }}">
                Driver: {{ strtoupper($driver) }}
            </span>
            @if($isMock)
            <form method="POST" action="{{ route('tiktok-shop.chats.reset-mock') }}" onsubmit="return confirm('Reset semua percakapan demo?');">
                @csrf
                <button type="submit" class="h-9 rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50" data-testid="tiktok-reset-demo">
                    Reset demo
                </button>
            </form>
            @endif
        </div>
    </div>

    @if(session('successMessage'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('successMessage') }}</div>
    @endif
    @if(session('errorMessage'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('errorMessage') }}</div>
    @endif

    @if($isMock)
    <details class="rounded-lg border border-dashed border-amber-300 bg-amber-50/80 px-4 py-3 text-sm text-amber-950">
        <summary class="cursor-pointer font-semibold">Mock Open API (Customer Service {{ config('tiktok_shop.api_version') }})</summary>
        <div class="mt-3 space-y-2 font-mono text-xs leading-relaxed">
            <p>Header: <code>x-tts-mock-token: {{ $mockApiTokenHint }}</code></p>
            <p>GET {{ $mockApiBase }}/conversations</p>
            <p>GET {{ $mockApiBase }}/conversations/{conversation_id}/messages</p>
            <p>POST {{ $mockApiBase }}/conversations/{conversation_id}/messages — body JSON <code>{"content":"..."}</code></p>
            <p>POST {{ $mockApiBase }}/conversations/{conversation_id}/simulate_buyer — mock buyer inbound</p>
        </div>
    </details>
    @endif

    <div class="grid min-h-[32rem] grid-cols-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:grid-cols-[minmax(14rem,22rem)_1fr]">
        {{-- Inbox --}}
        <aside class="border-b border-gray-200 lg:border-b-0 lg:border-r">
            <div class="border-b border-gray-100 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Inbox ({{ count($conversations) }})
            </div>
            <ul class="max-h-[28rem] divide-y divide-gray-100 overflow-y-auto lg:max-h-[calc(32rem-2.5rem)]">
                @forelse($conversations as $conv)
                @php
                    $active = $activeConversationId === ($conv['conversation_id'] ?? '');
                    $unread = (int) ($conv['unread_count'] ?? 0);
                @endphp
                <li>
                    <a href="{{ route('tiktok-shop.chats.index', ['conversation' => $conv['conversation_id']]) }}"
                       class="block px-3 py-3 transition-colors {{ $active ? 'bg-blue-50' : 'hover:bg-gray-50' }}"
                       data-testid="tiktok-conversation-{{ $conv['conversation_id'] }}">
                        <div class="flex items-start justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $conv['buyer_nickname'] ?? 'Buyer' }}</p>
                            @if($unread > 0)
                            <span class="shrink-0 rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span>
                            @endif
                        </div>
                        @if(!empty($conv['order_id']))
                        <p class="mt-0.5 truncate text-[11px] text-gray-500">Order {{ $conv['order_id'] }}</p>
                        @endif
                        <p class="mt-1 line-clamp-2 text-xs text-gray-600">{{ $conv['latest_message']['content'] ?? '—' }}</p>
                    </a>
                </li>
                @empty
                <li class="px-3 py-6 text-center text-sm text-gray-500">Belum ada percakapan.</li>
                @endforelse
            </ul>
        </aside>

        {{-- Thread --}}
        <section class="flex min-h-[24rem] flex-col">
            @if($activeConversation)
            <header class="border-b border-gray-100 px-4 py-3">
                <p class="text-sm font-semibold text-gray-900">{{ $activeConversation['buyer_nickname'] ?? 'Buyer' }}</p>
                <p class="text-xs text-gray-500">ID {{ $activeConversation['conversation_id'] }}</p>
            </header>

            <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" data-testid="tiktok-message-thread">
                @foreach($messages as $msg)
                @php
                    $role = strtoupper((string) ($msg['sender_role'] ?? ''));
                    $isSeller = $role === 'SELLER';
                    $isSystem = $role === 'SYSTEM';
                @endphp
                <div class="flex {{ $isSeller ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl px-3 py-2 text-sm {{ $isSystem ? 'bg-gray-100 text-gray-600 italic' : ($isSeller ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900') }}">
                        @if(!$isSystem)
                        <p class="mb-0.5 text-[10px] font-semibold uppercase opacity-70">{{ $role === 'SELLER' ? 'Seller (Aria)' : 'Buyer' }}</p>
                        @endif
                        <p class="whitespace-pre-wrap break-words">{{ $msg['content'] }}</p>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="border-t border-gray-100 p-4 space-y-3">
                @if($canReply)
                <form method="POST" action="{{ route('tiktok-shop.chats.reply', $activeConversationId) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    @csrf
                    <label class="flex-1">
                        <span class="sr-only">Balasan seller</span>
                        <textarea name="content" rows="2" required maxlength="2000" placeholder="Tulis balasan ke buyer…"
                                  class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                  data-testid="tiktok-reply-input"></textarea>
                    </label>
                    <button type="submit" class="h-10 shrink-0 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800" data-testid="tiktok-reply-submit">
                        Kirim balasan
                    </button>
                </form>
                @else
                <p class="text-sm text-gray-500">Anda tidak punya izin <code class="text-xs">tiktok-shop-reply</code>.</p>
                @endif

                @if($isMock)
                <form method="POST" action="{{ route('tiktok-shop.chats.simulate-buyer', $activeConversationId) }}" class="flex flex-col gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 sm:flex-row sm:items-end">
                    @csrf
                    <label class="flex-1">
                        <span class="text-xs font-semibold text-gray-600">Simulasi pesan buyer (mock)</span>
                        <input type="text" name="content" maxlength="2000" placeholder="Contoh: Sudah saya transfer"
                               class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                               data-testid="tiktok-simulate-buyer-input">
                    </label>
                    <button type="submit" class="h-9 shrink-0 rounded-lg border border-gray-400 bg-white px-3 text-sm font-medium text-gray-800 hover:bg-gray-100">
                        Simulasikan buyer
                    </button>
                </form>
                @endif
            </div>
            @else
            <div class="flex flex-1 items-center justify-center p-8 text-sm text-gray-500">
                Pilih percakapan di inbox.
            </div>
            @endif
        </section>
    </div>
</div>
@endsection
