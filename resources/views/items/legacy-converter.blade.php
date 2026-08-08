@extends('layouts.app')

@section('title', 'Legacy Item Identity Converter')

@section('content')
@php
    $typeLabel = $itemType === \App\Enums\ItemType::ASSET_LANCAR ? 'Asset Lancar' : 'Manufactured';
    $tabs = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ];
    $baseParams = ['type' => $itemType->value];
    $itemShowUrl = function ($item) use ($itemType) {
        if (! $item) {
            return null;
        }

        $type = $item->type ?? $itemType;

        return $type === \App\Enums\ItemType::ASSET_LANCAR
            ? route('assetlancar.show', $item)
            : route('items.show', $item);
    };
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Legacy Item Identity Converter</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Batch-convert legacy SKUs into canonical identity ({{ number_format($pendingCount) }} {{ strtolower($typeLabel) }} pending).
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('items.legacy-converter', array_merge($baseParams, ['type' => \App\Enums\ItemType::ASSET_LANCAR->value, 'tab' => $tab])) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $itemType === \App\Enums\ItemType::ASSET_LANCAR ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                Asset Lancar
            </a>
            <a href="{{ route('items.legacy-converter', array_merge($baseParams, ['type' => \App\Enums\ItemType::ITEM->value, 'tab' => $tab])) }}"
               class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $itemType === \App\Enums\ItemType::ITEM ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                Manufactured
            </a>
        </div>
    </div>

    @if($flash['success'] ?? false)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? false)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
        <h3 class="text-sm font-semibold text-amber-900">Prep — shrink the queue</h3>
        <dl class="mt-2 grid gap-2 text-sm text-amber-900 sm:grid-cols-2">
            <div>
                <dt class="font-medium">Useless SKUs (hard delete)</dt>
                <dd class="text-amber-800">{{ number_format($uselessCount) }} — created &gt;1 year ago, never in any transaction</dd>
            </div>
            <div>
                <dt class="font-medium">Super-old (excluded)</dt>
                <dd class="text-amber-800">{{ number_format($superOldCount) }} — created &gt;5 years ago, no transactions in last 2 years</dd>
            </div>
        </dl>
        @if($uselessCount > 0)
        <form method="POST" action="{{ route('items.legacy-converter.purge-useless') }}" class="mt-3"
              onsubmit="return confirm('Permanently delete up to {{ number_format($batchSize) }} useless {{ strtolower($typeLabel) }} SKUs? This cannot be undone.');">
            @csrf
            <input type="hidden" name="type" value="{{ $itemType->value }}">
            <button type="submit" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                Delete useless batch ({{ number_format(min($uselessCount, $batchSize)) }})
            </button>
        </form>
        @endif
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3">
        <div class="flex flex-wrap gap-1">
            @foreach($tabs as $key => $label)
                <a href="{{ route('items.legacy-converter', array_merge($baseParams, ['tab' => $key])) }}"
                   class="rounded-md px-3 py-1.5 text-sm font-medium {{ $tab === $key ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('items.legacy-converter.preview') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $itemType->value }}">
                <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Preview next batch ({{ number_format($batchSize) }})
                </button>
            </form>
            <form method="POST" action="{{ route('items.legacy-converter.run') }}" onsubmit="return confirm('Run conversion for up to {{ number_format($batchSize) }} {{ strtolower($typeLabel) }} items?');">
                @csrf
                <input type="hidden" name="type" value="{{ $itemType->value }}">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Run next batch
                </button>
            </form>
        </div>
    </div>

    @if($latestRun)
        <p class="text-xs text-gray-500">
            Last run #{{ $latestRun->id }}:
            {{ $latestRun->success_count }} success,
            {{ $latestRun->failed_count }} failed,
            {{ $latestRun->skipped_count }} skipped
            @if($latestRun->finished_at)
                — {{ $latestRun->finished_at->diffForHumans() }}
            @endif
        </p>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        @if($tab === 'pending')
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Group</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($dataList as $item)
                        @php $showUrl = $itemShowUrl($item); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-500">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="font-medium text-blue-600 hover:underline">#{{ $item->id }}</a>
                                @else
                                    {{ $item->id }}
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="text-blue-600 hover:underline">{{ $item->code }}</a>
                                @else
                                    <span class="text-gray-900">{{ $item->code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-700">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="hover:text-blue-600 hover:underline">{{ $item->name }}</a>
                                @else
                                    {{ $item->name }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500">{{ $item->group_id ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No pending items.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @elseif($tab === 'completed')
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Original</th>
                        <th class="px-4 py-3">Canonical</th>
                        <th class="px-4 py-3">Run</th>
                        <th class="px-4 py-3">When</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($dataList as $result)
                        @php $showUrl = $itemShowUrl($result->item); @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-500">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="font-medium text-blue-600 hover:underline">#{{ $result->item_id }}</a>
                                @else
                                    #{{ $result->item_id }}
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono text-gray-700">{{ $result->snapshot['original_code'] ?? '—' }}</td>
                            <td class="px-4 py-2 font-mono">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="text-blue-600 hover:underline">{{ $result->snapshot['canonical_code'] ?? $result->item?->code }}</a>
                                @else
                                    <span class="text-gray-900">{{ $result->snapshot['canonical_code'] ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500">#{{ $result->run_id }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $result->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No completed conversions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Failure</th>
                        <th class="px-4 py-3">Detail</th>
                        <th class="px-4 py-3">Run</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($dataList as $result)
                        @php
                            $showUrl = $itemShowUrl($result->item);
                            $code = $result->item?->code ?? ($result->snapshot['original_code'] ?? '—');
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-500">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="font-medium text-blue-600 hover:underline">#{{ $result->item_id }}</a>
                                @else
                                    #{{ $result->item_id }}
                                @endif
                            </td>
                            <td class="px-4 py-2 font-mono">
                                @if($showUrl)
                                    <a href="{{ $showUrl }}" class="text-blue-600 hover:underline">{{ $code }}</a>
                                @else
                                    <span class="text-gray-700">{{ $code }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">
                                <span class="rounded bg-red-100 px-2 py-0.5 font-mono text-xs font-semibold text-red-800">{{ $result->failure_code }}</span>
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $result->detail }}</td>
                            <td class="px-4 py-2 text-gray-500">#{{ $result->run_id }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No failures recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    @if(method_exists($dataList, 'links'))
        <div>{{ $dataList->links() }}</div>
    @endif
</div>
@endsection
