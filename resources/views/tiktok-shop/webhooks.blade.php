@extends('layouts.app')

@section('title', 'TikTok Shop Webhook Inbox')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'TikTok Shop', 'href' => route('tiktok-shop.chats.index')],
    ['title' => 'Webhook Inbox', 'href' => route('tiktok-shop.webhooks.index')],
];
$th = 'px-2 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500';
$td = 'px-2 py-2 align-top text-xs text-gray-800';
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4"
     data-testid="tiktok-shop-webhooks-page"
     x-data="{
         expandedId: null,
         toggleRow(id) {
             this.expandedId = this.expandedId === id ? null : id;
         },
         isExpanded(id) {
             return this.expandedId === id;
         }
     }">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
            <h1 class="flex items-center gap-2 text-2xl font-bold text-gray-900">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-900 text-xs font-bold text-white">TT</span>
                TikTok Shop — Webhook Inbox
            </h1>
            <p class="mt-1 max-w-2xl text-sm text-gray-600">
                Read-only log of inbound Event API webhooks (<code class="rounded bg-gray-100 px-1 text-xs">POST /tiktok-shop/webhook</code>).
                Tidak mem-post order atau memanggil API TikTok.
            </p>
        </div>
    </div>

    @if(!$tableExists)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-950" data-testid="tiktok-webhooks-missing-table">
        <p class="font-semibold">Tabel belum tersedia</p>
        <p class="mt-1 text-amber-900">
            Jalankan migrasi di server yang menerima webhook TikTok:
        </p>
        <pre class="mt-2 overflow-x-auto rounded-md bg-white/80 px-3 py-2 font-mono text-xs text-gray-800">php artisan migrate --path=database/migrations/2026_11_21_120000_create_tiktok_shop_webhook_inbox_table.php</pre>
    </div>
    @elseif($rows->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-600 shadow-sm" data-testid="tiktok-webhooks-empty">
        Belum ada webhook tersimpan. Kirim uji dari Partner Center atau tunggu perubahan status order.
    </div>
    @else
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-3 py-2 text-xs text-gray-500">
            {{ number_format($rows->total()) }} event — halaman {{ $rows->currentPage() }} / {{ $rows->lastPage() }}
        </div>
        <div class="min-w-0 overflow-x-auto">
            <table class="w-full min-w-[56rem] text-left" data-testid="tiktok-webhooks-table">
                <thead class="border-b border-gray-200 bg-gray-50">
                    <tr>
                        <th class="{{ $th }} w-14">ID</th>
                        <th class="{{ $th }} w-36">created_at</th>
                        <th class="{{ $th }} w-16">type</th>
                        <th class="{{ $th }}">order_id</th>
                        <th class="{{ $th }} w-28">order_status</th>
                        <th class="{{ $th }}">shop_id</th>
                        <th class="{{ $th }}">tts_notification_id</th>
                        <th class="{{ $th }} w-20 text-center">sign OK</th>
                        <th class="{{ $th }} w-24 text-right">payload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                    @php
                        $payloadJson = json_encode($row->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $payloadPreview = \Illuminate\Support\Str::limit($payloadJson, 80, '…');
                    @endphp
                    <tr class="hover:bg-gray-50/80">
                        <td class="{{ $td }} font-mono text-[11px]">{{ $row->id }}</td>
                        <td class="{{ $td }} whitespace-nowrap text-gray-600">{{ $row->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                        <td class="{{ $td }} font-mono">{{ $row->event_type ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->order_id ?? '—' }}</td>
                        <td class="{{ $td }}">{{ $row->order_status ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->shop_id ?? '—' }}</td>
                        <td class="{{ $td }} font-mono text-[11px] break-all">{{ $row->tts_notification_id }}</td>
                        <td class="{{ $td }} text-center">
                            @if($row->signature_ok)
                            <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-800">ya</span>
                            @else
                            <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">tidak</span>
                            @endif
                        </td>
                        <td class="{{ $td }} text-right">
                            <button type="button"
                                    @click="toggleRow(@js($row->id))"
                                    class="rounded-md border border-gray-300 bg-white px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-testid="tiktok-webhook-expand-{{ $row->id }}"
                                    x-text="isExpanded(@js($row->id)) ? 'Tutup' : 'Lihat'">
                                Lihat
                            </button>
                        </td>
                    </tr>
                    <tr x-show="isExpanded(@js($row->id))" x-cloak class="bg-gray-50">
                        <td colspan="9" class="px-3 py-3">
                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Raw payload (read-only)</p>
                            <p class="mb-2 font-mono text-[11px] text-gray-500 lg:hidden">{{ $payloadPreview }}</p>
                            <pre class="max-h-64 overflow-auto rounded-lg border border-gray-200 bg-white p-3 font-mono text-[11px] leading-relaxed text-gray-800 whitespace-pre-wrap break-all">{{ json_encode($row->payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
        <div class="border-t border-gray-100 px-3 py-3">
            {{ $rows->links() }}
        </div>
        @endif
    </div>
    @endif
</div>
@endsection