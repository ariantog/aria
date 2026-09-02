@if(!empty($stockErrorItems))
<div class="max-w-full">
    <p class="text-[10px] font-medium text-red-600">Stok tidak cukup:</p>
    <div class="mt-1 flex flex-wrap justify-start gap-1">
        @foreach($stockErrorItems as $row)
            @if(!empty($row['item_id']))
            <a href="{{ route('items.show', $row['item_id']) }}"
               class="inline-flex rounded border border-red-200 bg-red-50 px-1.5 py-0.5 font-mono text-[10px] text-red-700 hover:bg-red-100 hover:underline"
               title="Stok: {{ format_amount($row['available'] ?? 0, 0) }}, butuh: {{ format_amount($row['needed'] ?? 0, 0) }}">
                {{ $row['code'] ?? '—' }}
            </a>
            @else
            <span class="inline-flex rounded border border-red-200 bg-red-50 px-1.5 py-0.5 font-mono text-[10px] text-red-700">
                {{ $row['code'] ?? '—' }}
            </span>
            @endif
        @endforeach
    </div>
</div>
@endif
