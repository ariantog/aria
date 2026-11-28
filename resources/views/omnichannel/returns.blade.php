@extends('layouts.app')

@section('title', 'Marketplace returns')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Marketplace returns', 'href' => route('omnichannel.returns.index')],
];
$th = 'px-2 py-2 text-[10px] font-semibold tracking-wide text-gray-500 uppercase';
$td = 'px-2 py-2 align-top text-sm';
$status = $filters['status'] ?? 'pending_verification';
$buildTabUrl = function (string $tabStatus) use ($filters) {
    return route('omnichannel.returns.index', array_filter([
        'status' => $tabStatus,
        'channel' => ($filters['channel'] ?? 'all') !== 'all' ? $filters['channel'] : null,
        'invoice' => $filters['invoice'] ?? null,
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'per_page' => $filters['per_page'] ?? null,
    ], fn ($v) => $v !== null && $v !== ''));
};
$cards = [
    ['key' => 'pending_verification', 'title' => 'Menunggu verifikasi', 'border' => 'border-amber-500', 'bg' => 'bg-amber-50 text-amber-800'],
    ['key' => 'verified', 'title' => 'Terverifikasi', 'border' => 'border-blue-500', 'bg' => 'bg-blue-50 text-blue-800'],
    ['key' => 'posted', 'title' => 'Sudah diposting', 'border' => 'border-green-500', 'bg' => 'bg-green-50 text-green-800'],
    ['key' => 'all', 'title' => 'Semua', 'border' => 'border-gray-400', 'bg' => 'bg-gray-50 text-gray-700'],
];
@endphp

<div class="flex flex-col gap-4 p-2 sm:gap-6 sm:p-4" data-testid="omnichannel-returns-page">
    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Marketplace returns</h1>
            <p class="mt-1 text-sm text-gray-600">Shopee, TikTok Shop, dan Tokopedia — verifikasi penerimaan fisik sebelum posting RETURN.</p>
        </div>

        <form method="GET" action="{{ route('omnichannel.returns.index') }}" class="flex w-full flex-wrap items-end gap-2 md:w-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <label class="flex min-w-[8rem] flex-col gap-0.5">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Channel</span>
                <select name="channel" class="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm">
                    <option value="all" @selected(($filters['channel'] ?? 'all') === 'all')>Semua</option>
                    <option value="shopee" @selected(($filters['channel'] ?? '') === 'shopee')>Shopee</option>
                    <option value="tiktok" @selected(($filters['channel'] ?? '') === 'tiktok')>TikTok Shop</option>
                    <option value="tokopedia" @selected(($filters['channel'] ?? '') === 'tokopedia')>Tokopedia</option>
                </select>
            </label>
            <label class="flex min-w-[9rem] flex-col gap-0.5">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Order / invoice</span>
                <input type="text" name="invoice" value="{{ $filters['invoice'] ?? '' }}" placeholder="Order ref…" class="h-9 rounded-md border border-gray-300 px-2 text-sm md:w-40">
            </label>
            <label class="flex flex-col gap-0.5">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Dari</span>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="h-9 rounded-md border border-gray-300 px-2 text-sm">
            </label>
            <label class="flex flex-col gap-0.5">
                <span class="text-[10px] font-semibold uppercase text-gray-500">Sampai</span>
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="h-9 rounded-md border border-gray-300 px-2 text-sm">
            </label>
            <button type="submit" class="h-9 rounded-lg bg-blue-700 px-3 text-sm font-medium text-white hover:bg-blue-800">Filter</button>
        </form>
    </div>

    @if(session('success'))
    <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900" data-testid="omnichannel-returns-flash-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" data-testid="omnichannel-returns-flash-error">{{ session('error') }}</div>
    @endif
    @if(session('warning'))
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
    @endif

    @if(!$tableReady)
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900">Tabel marketplace belum tersedia — jalankan migrasi.</div>
    @else
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($cards as $card)
        @php $isActive = $status === $card['key'] || ($status === '' && $card['key'] === 'pending_verification'); @endphp
        <a href="{{ $buildTabUrl($card['key']) }}"
           class="rounded-lg border-l-4 {{ $card['border'] }} {{ $card['bg'] }} p-3 shadow-sm {{ $isActive ? 'ring-2 ring-offset-1 ring-gray-400' : '' }}"
           data-testid="omnichannel-returns-tab-{{ $card['key'] }}">
            <p class="text-[10px] font-semibold uppercase opacity-80">{{ $card['title'] }}</p>
            <p class="text-2xl font-bold">{{ number_format($stats[$card['key']] ?? 0) }}</p>
        </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full min-w-[56rem] text-left" data-testid="omnichannel-returns-table">
            <thead class="border-b border-gray-100 bg-gray-50">
                <tr>
                    <th class="{{ $th }}">Channel</th>
                    <th class="{{ $th }}">Order</th>
                    <th class="{{ $th }}">Status platform</th>
                    <th class="{{ $th }}">Sell invoice</th>
                    <th class="{{ $th }}">Workflow</th>
                    <th class="{{ $th }}">Terakhir event</th>
                    <th class="{{ $th }} text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                @php
                    $workflowStatus = $workflow->workflowStatus($row);
                    $sellInvoice = $eligibility->resolveSellInvoice($row);
                    $reject = $rejectByOrderId[$row->id] ?? null;
                    $channelLabel = strtolower((string) $row->channel) === 'tiktok'
                        && strtoupper((string) ($row->commerce_platform ?? '')) === 'TOKOPEDIA'
                        ? 'Tokopedia'
                        : ucfirst((string) $row->channel);
                @endphp
                <tr data-testid="omnichannel-returns-row-{{ $row->id }}">
                    <td class="{{ $td }}">{{ $channelLabel }}</td>
                    <td class="{{ $td }} font-mono text-xs">{{ $row->order_ref }}</td>
                    <td class="{{ $td }}">{{ $row->order_status }}</td>
                    <td class="{{ $td }} font-mono text-xs">{{ $sellInvoice !== '' ? $sellInvoice : '—' }}</td>
                    <td class="{{ $td }}">
                        @if($workflowStatus === 'posted')
                            <span class="rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Posted</span>
                        @elseif($workflowStatus === 'verified')
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Verified</span>
                            @if($row->returnVerifiedBy)
                                <span class="block text-[11px] text-gray-500">{{ $row->returnVerifiedBy->username }}</span>
                            @endif
                        @else
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Pending verify</span>
                        @endif
                        @if($reject && $workflowStatus !== 'posted')
                            <p class="mt-1 text-[11px] text-gray-500">{{ $reject }}</p>
                        @endif
                    </td>
                    <td class="{{ $td }} text-xs text-gray-600">{{ optional($row->last_event_at)->format('Y-m-d H:i') ?? '—' }}</td>
                    <td class="{{ $td }} text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            @if($verifyReady && $workflowStatus === 'pending_verification')
                            <form method="POST" action="{{ route('omnichannel.returns.verify', $row) }}" class="inline">
                                @csrf
                                <button type="submit"
                                        class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100"
                                        data-testid="omnichannel-returns-verify-{{ $row->id }}">
                                    Verify
                                </button>
                            </form>
                            @endif
                            @if($workflowStatus === 'verified' && $eligibility->masterEnabled())
                            <form method="POST" action="{{ route('omnichannel.returns.post', $row) }}" class="inline" onsubmit="return confirm('Post RETURN untuk order ini?');">
                                @csrf
                                <button type="submit"
                                        class="rounded border border-green-300 bg-green-50 px-2 py-1 text-xs font-medium text-green-800 hover:bg-green-100"
                                        data-testid="omnichannel-returns-post-{{ $row->id }}">
                                    Post
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">Tidak ada return pada filter ini.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows && $rows->hasPages())
    <div class="text-sm">{{ $rows->links() }}</div>
    @endif
    @endif
</div>
@endsection