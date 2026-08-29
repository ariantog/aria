@php
    $fmt = $fmt ?? fn ($n) => format_amount($n);
    $itemView = $itemView ?? \App\Support\TransactionItemViewOptions::defaults();
    $forPdf = $forPdf ?? false;
    $leadingCols = \App\Support\TransactionItemViewOptions::leadingColumnCount($itemView);
    $totalCols = $leadingCols + 4;
    $labelColspan = max(1, $totalCols - 1);
@endphp

<table class="invoice-items" style="margin-top:12px; width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            @if($itemView['showImage'])
                <th style="text-align:center;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Img</th>
            @endif
            @if($itemView['showBarcode'])
                <th style="text-align:left;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Barcode</th>
            @endif
            @if($itemView['showSku'])
                <th style="text-align:left;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">SKU</th>
            @endif
            @if($itemView['showName'])
                <th style="text-align:left;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Item Name</th>
            @endif
            @if($itemView['showDescription'])
                <th style="text-align:left;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Desc</th>
            @endif
            <th style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Qty</th>
            <th style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Price</th>
            <th style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Disc(%)</th>
            <th style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transaction->details as $detail)
            @php $item = $detail->item; @endphp
            <tr>
                @if($itemView['showImage'])
                    <td style="text-align:center; vertical-align:middle;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">
                        @if($item && $forPdf && file_exists($item->image_path))
                            <img src="{{ $item->image_path }}" alt="" style="max-height:48px; max-width:48px;">
                        @elseif($item && ! $forPdf)
                            <img src="{{ $item->image_url }}" alt="" style="max-height:48px; max-width:48px;">
                        @endif
                    </td>
                @endif
                @if($itemView['showBarcode'])
                    <td style="font-family:monospace; font-size:11px;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $item?->id ?? '-' }}</td>
                @endif
                @if($itemView['showSku'])
                    <td style="font-family:monospace; font-size:11px; font-style:italic;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $item?->code ?: '-' }}</td>
                @endif
                @if($itemView['showName'])
                    <td style="{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">
                        <div style="font-weight:bold;">{{ $item?->getItemName() ?? '-' }}</div>
                        @if($item?->code)
                            <div style="margin-top:2px; font-family:monospace; font-size:10px; color:#666;">{{ $item->code }}</div>
                        @endif
                        @if($detail->notes)
                            <div style="margin-top:4px; font-size:11px; font-style:italic; color:#666;">{{ $detail->notes }}</div>
                        @endif
                    </td>
                @endif
                @if($itemView['showDescription'])
                    <td style="{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $item?->description ?: '-' }}</td>
                @endif
                <td style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $fmt($detail->quantity) }}</td>
                <td style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $fmt($detail->price) }}</td>
                <td style="text-align:right;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">
                    @if((float) $detail->discount > 0)
                        -{{ $fmt((float) $detail->discount) }}%
                    @else
                        -
                    @endif
                </td>
                <td style="text-align:right; font-weight:bold;{{ $forPdf ? '' : ' padding:6px 8px; border:1px solid #ccc;' }}">{{ $fmt($detail->total) }}</td>
            </tr>
        @endforeach
    </tbody>
    @if(! ($forPdf ?? false))
    <tfoot>
        <tr><td colspan="{{ $totalCols }}"><hr></td></tr>
        <tr>
            <td colspan="{{ $labelColspan }}" style="text-align:right;">Subtotal</td>
            <td style="text-align:right;">{{ $fmt($transaction->total) }}</td>
        </tr>
        @if((float) $transaction->discount > 0)
            <tr>
                <td colspan="{{ $labelColspan }}" style="text-align:right;">Discount</td>
                <td style="text-align:right;">-{{ $fmt($transaction->discount) }}</td>
            </tr>
        @endif
        @if((float) $transaction->adjustment != 0)
            <tr>
                <td colspan="{{ $labelColspan }}" style="text-align:right;">Adjustment</td>
                <td style="text-align:right;">{{ $fmt($transaction->adjustment) }}</td>
            </tr>
        @endif
        @if((float) $transaction->ppn > 0)
            <tr>
                <td colspan="{{ $labelColspan }}" style="text-align:right;">PPN</td>
                <td style="text-align:right;">{{ $fmt($transaction->ppn) }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="{{ $labelColspan }}" style="text-align:right;"><strong>Grand Total</strong></td>
            <td style="text-align:right;"><strong>{{ $fmt(abs((float) $transaction->real_total)) }}</strong></td>
        </tr>
    </tfoot>
    @endif
</table>
