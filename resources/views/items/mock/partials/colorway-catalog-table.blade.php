@php
$titleBadgeClass = $titleBadgeClass ?? fn (string $source) => match ($source) {
    'parent' => 'bg-blue-100 text-blue-800',
    'colorway' => 'bg-purple-100 text-purple-800',
    'pcode' => 'bg-amber-100 text-amber-900',
    'sku' => 'bg-gray-200 text-gray-800',
    default => 'bg-gray-100 text-gray-700',
};
@endphp
<div class="overflow-x-auto rounded-lg border border-gray-200">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3 text-left">Color</th>
                <th class="px-4 py-3 text-left">Style code</th>
                <th class="px-4 py-3 text-left">Effective bare title</th>
                <th class="px-4 py-3 text-left">Source</th>
                <th class="px-4 py-3 text-left">Example list name</th>
                <th class="px-4 py-3 text-right"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse($colorwayCatalogRows as $row)
            <tr class="hover:bg-gray-50/80">
                <td class="px-4 py-3">
                    <span class="font-semibold text-gray-900">{{ $row['warna_code'] }}</span>
                    <span class="text-gray-500">{{ $row['warna_name'] }}</span>
                </td>
                <td class="px-4 py-3 font-mono text-gray-800">{{ $row['pcode'] }}</td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $row['effective_title'] ?: '—' }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $titleBadgeClass($row['title_source']) }}">
                        {{ $row['title_source_label'] }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">{{ $row['sample_display_name'] }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    @if($canEditGroup ?? false)
                    <a href="{{ route('items.catalog-mock.colorway', $row['group_id']) }}" class="text-indigo-600 hover:underline">Mock</a>
                    @if($showLiveLinks ?? false)
                    <span class="mx-1 text-gray-300">|</span>
                    <a href="{{ route('items.colorway-edit', $row['group_id']) }}" class="text-gray-500 hover:underline">Live</a>
                    @endif
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No colorways.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
