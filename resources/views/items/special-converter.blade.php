@extends('layouts.app')

@section('title', 'Special SKU Converter')

@section('content')
@php
    $preservedLegacyCode = function ($item) {
        $legacy = strtoupper(trim((string) ($item->legacy_code ?? '')));
        $code = strtoupper(trim((string) ($item->code ?? '')));

        return $legacy !== '' && $legacy !== $code ? $legacy : null;
    };
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Special SKU Converter</h2>
            <p class="mt-0.5 text-sm text-gray-500">
                Convert hardcoded legacy SKU families that do not fit the generic parser.
                {{ number_format($pendingCount) }} asset lancar item(s) pending.
                @if($currentPageCount > 0)
                    Page {{ $currentPage }} shows {{ number_format($currentPageCount) }} item(s); {{ number_format($convertiblePageCount) }} ready (Legacy column empty).
                @endif
            </p>
        </div>
        <a href="{{ route('items.legacy-converter') }}"
           class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Generic Legacy Converter
        </a>
    </div>

    @if($flash['success'] ?? false)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? false)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
        <h3 class="text-sm font-semibold text-blue-900">Registered rules</h3>
        <ul class="mt-2 space-y-3 text-sm text-blue-900">
            @foreach($families as $family)
                <li>
                    <p class="font-medium">{{ $family['label'] }}</p>
                    <p class="text-blue-800">
                        Legacy: <span class="font-mono">{{ $family['legacy_example'] }}</span>
                        → Canonical: <span class="font-mono">{{ $family['canonical_example'] }}</span>
                    </p>
                    <p class="text-blue-700">
                        Parent <span class="font-mono">{{ explode('-', $family['legacy_example'])[0] }}-{{ explode('-', $family['legacy_example'])[1] }}</span>,
                        sizes {{ implode(' / ', $family['sizes']) }}.
                        Old SKU is preserved in <span class="font-mono">legacy_code</span> for Jubelio order matching.
                    </p>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3">
        @if($convertiblePageCount > 0)
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('items.special-converter.preview') }}">
                @csrf
                <input type="hidden" name="page" value="{{ $currentPage }}">
                @foreach($dataList as $item)
                    @if($preservedLegacyCode($item) === null)
                <input type="hidden" name="item_ids[]" value="{{ $item->id }}">
                    @endif
                @endforeach
                <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Preview page ({{ number_format($convertiblePageCount) }})
                </button>
            </form>
            <form method="POST" action="{{ route('items.special-converter.run') }}"
                  onsubmit="return confirm('Convert {{ number_format($convertiblePageCount) }} special SKU(s) on page {{ $currentPage }}? Old codes move to legacy_code for Jubelio.');">
                @csrf
                <input type="hidden" name="page" value="{{ $currentPage }}">
                @foreach($dataList as $item)
                    @if($preservedLegacyCode($item) === null)
                <input type="hidden" name="item_ids[]" value="{{ $item->id }}">
                    @endif
                @endforeach
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Convert this page ({{ number_format($convertiblePageCount) }})
                </button>
            </form>
        </div>
        @else
        <p class="text-sm text-gray-500">No convertible items on this page.</p>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3">ID</th>
                    <th class="px-4 py-3">Current code</th>
                    <th class="px-4 py-3">New code</th>
                    <th class="px-4 py-3">Legacy</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Jubelio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($dataList as $item)
                    @php
                        $preview = $previews[$item->id]['parse'] ?? null;
                        $legacy = $preservedLegacyCode($item);
                        $showUrl = route('assetlancar.show', $item);
                    @endphp
                    <tr class="hover:bg-gray-50 {{ $legacy ? 'bg-gray-50/80' : '' }}">
                        <td class="px-4 py-2 text-gray-500">
                            <a href="{{ $showUrl }}" class="font-medium text-blue-600 hover:underline">#{{ $item->id }}</a>
                        </td>
                        <td class="px-4 py-2 font-mono">
                            <a href="{{ $showUrl }}" class="text-blue-600 hover:underline">{{ $item->code }}</a>
                        </td>
                        <td class="px-4 py-2 font-mono text-green-700">
                            @if($preview?->success)
                                {{ $preview->canonicalCode }}
                            @elseif($preview)
                                <span class="text-red-600" title="{{ $preview->detail }}">{{ $preview->failureCode }}</span>
                                @if($preview->detail)
                                    <span class="block text-xs text-red-500">{{ $preview->detail }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 font-mono text-xs {{ $legacy ? 'text-amber-700' : 'text-gray-400' }}">
                            {{ $legacy ?? ($preview?->success ? $item->code : '—') }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $item->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $item->jubelio_item_id ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No pending special SKUs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($dataList->hasPages())
        <div class="flex justify-center">
            {{ $dataList->links() }}
        </div>
    @endif
</div>
@endsection
