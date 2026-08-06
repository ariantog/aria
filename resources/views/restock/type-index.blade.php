@extends('layouts.app')

@section('title', 'Restock')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Stuff', 'href' => '#'],
    ['title' => 'Restock', 'href' => route('restock.index')],
];
if ($activeTypeTag) {
    $breadcrumbs[] = ['title' => $activeTypeTag->name, 'href' => route('restock.type.show', $activeTypeTag)];
}
@endphp

<div class="flex flex-col gap-4 p-4">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Restock</h1>
            <p class="text-sm text-gray-500">Asset lancar pipeline by product type.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @if($typeTags->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
            No TYPE tags found. Create TYPE tags under Stuff → Tags first.
        </div>
    @else
        @include('restock.partials.type-tabs')

        @if($activeTypeTag)
            @if(($canCreate ?? false) && $availableParents->isNotEmpty())
                <div class="rounded-xl border border-gray-200 bg-white p-4" x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                        + New sheet
                    </button>
                    <form x-show="open" x-cloak method="POST" action="{{ route('restock.sheets.store', $activeTypeTag) }}"
                          class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <div class="flex-1">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Parent (pcode)</label>
                            <select name="pcode" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                                <option value="">— Select parent —</option>
                                @foreach($availableParents as $parent)
                                    <option value="{{ $parent['pcode'] }}">{{ $parent['name'] }} — {{ $parent['pcode'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            Create sheet
                        </button>
                    </form>
                </div>
            @endif

            @if($parents->isEmpty())
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center text-gray-500">
                    No asset lancar items found for {{ $activeTypeTag->name }}.
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Product</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600">Pcode</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Restock</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Production</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600">Shipped</th>
                                <th class="px-4 py-3 text-center font-medium text-gray-600">Urgent</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($parents as $parent)
                                @php
                                    $sheet = $parent['sheet'];
                                    $imageUrl = $sheet
                                        ? $sheet->image_url
                                        : ($parent['representative_group_id']
                                            ? \App\Models\ItemGroup::find($parent['representative_group_id'])?->image_url
                                            : asset('images/default-item.png'));
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $imageUrl }}" alt="" class="h-10 w-10 rounded-md border border-gray-200 object-cover">
                                            <span class="font-medium text-gray-900">{{ $parent['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $parent['pcode'] }}</td>
                                    @if($sheet)
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($parent['totals']['restock']) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($parent['totals']['production']) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($parent['totals']['shipped']) }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($parent['urgent_count'] > 0)
                                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ $parent['urgent_count'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('restock.sheets.show', $sheet) }}"
                                               class="text-blue-600 hover:text-blue-800 font-medium">Open sheet</a>
                                        </td>
                                    @else
                                        <td colspan="4" class="px-4 py-3 text-gray-400">Not tracked yet</td>
                                        <td class="px-4 py-3 text-right">
                                            @if($canCreate ?? false)
                                                <form method="POST" action="{{ route('restock.sheets.store', $activeTypeTag) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="pcode" value="{{ $parent['pcode'] }}">
                                                    <button type="submit" class="text-blue-600 hover:text-blue-800 font-medium">Start tracking</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    @endif
</div>
@endsection
