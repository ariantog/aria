<div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
            <tr>
                <th class="px-3 py-2">Line</th>
                <th class="px-3 py-2">SKU Aria</th>
                <th class="px-3 py-2 text-right">Shopee item</th>
                <th class="px-3 py-2 text-right">Model</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Catatan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach($rows as $row)
            @php
                $status = (string) ($row['status'] ?? '');
                $statusClass = match ($status) {
                    'ready' => 'bg-blue-50 text-blue-800',
                    'linked' => 'bg-emerald-50 text-emerald-800',
                    'skipped' => 'bg-gray-100 text-gray-600',
                    default => 'bg-red-50 text-red-800',
                };
                $item = $row['item'] ?? null;
            @endphp
            <tr class="align-top">
                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-500">{{ $row['line'] ?? '—' }}</td>
                <td class="px-3 py-2">
                    @if($item)
                        <a href="{{ $item['show_url'] ?? route('items.show', $item['id']) }}" class="font-mono text-xs text-blue-600 hover:underline">{{ $item['code'] }}</a>
                    @else
                        <span class="font-mono text-xs text-gray-700">{{ $row['code'] ?? '—' }}</span>
                    @endif
                </td>
                <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs">{{ $row['shopee_item_id'] ?? '—' }}</td>
                <td class="whitespace-nowrap px-3 py-2 text-right font-mono text-xs">{{ $row['shopee_model_id'] ?? '—' }}</td>
                <td class="whitespace-nowrap px-3 py-2">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase {{ $statusClass }}">{{ $status ?: '?' }}</span>
                </td>
                <td class="max-w-md px-3 py-2 text-xs text-gray-600">{{ $row['message'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
