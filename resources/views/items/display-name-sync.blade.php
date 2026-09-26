@extends('layouts.app')

@section('title', 'Display Name Sync')

@section('content')
<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Display Name Sync</h2>
            <p class="mt-1 max-w-3xl text-sm text-gray-600">
                After legacy SKU conversion, newer rows often show technical names (e.g. <span class="font-mono text-xs">CLN CI00098/06 XL</span>).
                This tool copies the legacy pattern from sibling SKUs (e.g. <span class="font-mono text-xs">CORE SHORTS - ARMY GREEN - XL</span>)
                by setting the parent product title and rebuilding <span class="font-mono text-xs">items.name</span> from catalog + warna + size tags.
            </p>
            <p class="mt-2 text-sm">
                <a href="{{ route('items.legacy-converter') }}" class="text-blue-600 hover:underline">Legacy Item Identity Converter</a>
            </p>
        </div>
    </div>

    @if($flash['success'] ?? false)
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ $flash['success'] }}</div>
    @endif
    @if($flash['error'] ?? false)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $flash['error'] }}</div>
    @endif

    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('items.display-name-sync') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <input type="hidden" name="type" value="{{ $itemType->value }}">
            <div class="flex-1">
                <label for="needle" class="block text-sm font-medium text-gray-700">Search pcode or SKU</label>
                <input type="text"
                       id="needle"
                       name="needle"
                       value="{{ $needle }}"
                       placeholder="e.g. CI00098 or CLN-CI00098-04-XL"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       data-testid="display-name-sync-needle">
            </div>
            <button type="submit"
                    class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    data-testid="display-name-sync-search">
                Find product groups
            </button>
        </form>
    </div>

    @if($needle !== '' && $parentKeys === [])
        <p class="text-sm text-gray-600">No manufactured items matched &ldquo;{{ $needle }}&rdquo;.</p>
    @endif

    @if(count($parentKeys) > 1)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Multiple product groups share this search. Pick one to preview:
        </div>
        <ul class="flex flex-wrap gap-2">
            @foreach($parentKeys as $key)
                <li>
                    <a href="{{ route('items.display-name-sync', ['needle' => $needle, 'parent_key' => $key, 'type' => $itemType->value]) }}"
                       class="inline-block rounded-lg border px-3 py-1.5 text-sm font-medium {{ $parentKey === $key ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        {{ $key }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if($preview)
        <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-4 py-3">
                <h3 class="text-lg font-semibold text-gray-900">{{ $preview['label'] }}</h3>
                <p class="mt-1 text-sm text-gray-600">
                    {{ number_format($preview['item_count']) }} SKU(s);
                    {{ number_format($preview['would_change']) }} would change.
                    @if($preview['inferred_title'])
                        Inferred title from legacy names: <strong>{{ $preview['inferred_title'] }}</strong>.
                    @else
                        <span class="text-amber-700">Could not infer a title — enter one before applying.</span>
                    @endif
                </p>
            </div>

            <form method="POST" action="{{ route('items.display-name-sync.apply') }}" class="border-b border-gray-200 px-4 py-3"
                  onsubmit="return confirm('Rebuild display names for all SKUs in this product group?');">
                @csrf
                <input type="hidden" name="parent_key" value="{{ $preview['parent_key'] }}">
                <input type="hidden" name="needle" value="{{ $needle }}">
                <input type="hidden" name="type" value="{{ $itemType->value }}">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="product_title" class="block text-sm font-medium text-gray-700">Product title (parent group)</label>
                        <input type="text"
                               id="product_title"
                               name="product_title"
                               value="{{ old('product_title', $preview['inferred_title']) }}"
                               placeholder="CORE SHORTS"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                               data-testid="display-name-sync-product-title">
                        <p class="mt-1 text-xs text-gray-500">Leave blank to use inferred title when available.</p>
                    </div>
                    <button type="submit"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                            data-testid="display-name-sync-apply"
                            @if(! $preview['inferred_title']) title="Enter a product title if inference failed" @endif>
                        Apply to all SKUs
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm" data-testid="display-name-sync-preview-table">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">ID</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">SKU</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Current name</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Proposed name</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($preview['rows'] as $row)
                            <tr class="{{ $row['changes'] ? 'bg-amber-50/60' : '' }}">
                                <td class="whitespace-nowrap px-3 py-2 text-gray-700">{{ $row['id'] }}</td>
                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs">{{ $row['code'] }}</td>
                                <td class="px-3 py-2 text-gray-800">{{ $row['current'] }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ $row['proposed'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
