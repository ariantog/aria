@php
    $fmt = $fmt ?? fn ($n) => format_amount($n);
    $itemView = $itemView ?? \App\Support\TransactionItemViewOptions::defaults();
    $forPdf = $forPdf ?? false;
    $plainPrint = $plainPrint ?? false;
    $leadingCols = \App\Support\TransactionItemViewOptions::leadingColumnCount($itemView);
    $totalCols = $leadingCols + 4;
    $labelColspan = max(1, $totalCols - 1);
    $cellStyle = ($forPdf || $plainPrint) ? '' : ' padding:6px 8px; border:1px solid #ccc;';
@endphp

<table class="invoice-items" @if(! $plainPrint) style="margin-top:12px; width:100%; border-collapse:collapse;" @endif width="100%">
    <thead>
        <tr>
            @if($itemView['showImage'])
                <th style="text-align:center;{{ $cellStyle }}">Img</th>
            @endif
            @if($itemView['showBarcode'])
                <th style="text-align:left;{{ $cellStyle }}">Barcode</th>
            @endif
            @if(\App\Support\TransactionItemViewOptions::showSkuColumn($itemView))
                <th style="text-align:left;{{ $cellStyle }}">{{ \App\Support\TransactionItemViewOptions::skuColumnLabel($itemView) }}</th>
            @endif
            @if($itemView['showName'])
                <th style="text-align:left;{{ $cellStyle }}">{{ $plainPrint ? 'Code' : 'Item Name' }}</th>
            @endif
            @if($itemView['showDescription'])
                <th style="text-align:left;{{ $cellStyle }}">Desc</th>
            @endif
            <th class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">Qty</th>
            <th class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">Price</th>
            <th class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">Disc(%)</th>
            <th class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">{{ $plainPrint ? 'Total' : 'Subtotal' }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($transaction->details as $detail)
            @php $item = $detail->item; @endphp
            <tr>
                @if($itemView['showImage'])
                    <td style="text-align:center; vertical-align:middle;{{ $cellStyle }}">
                        @if($item && $forPdf && file_exists($item->image_path))
                            <img src="{{ $item->image_path }}" alt="" style="max-height:48px; max-width:48px;">
                        @elseif($item && ! $forPdf)
                            <img src="{{ $item->image_url }}" alt="" style="max-height:48px; max-width:48px;">
                        @endif
                    </td>
                @endif
                @if($itemView['showBarcode'])
                    <td style="{{ $plainPrint ? '' : 'font-family:monospace; font-size:11px;' }}{{ $cellStyle }}">{{ $item?->id ?? '-' }}</td>
                @endif
                @if(\App\Support\TransactionItemViewOptions::showSkuColumn($itemView))
                    <td style="{{ $plainPrint ? '' : 'font-family:monospace; font-size:11px; font-style:italic;' }}{{ $cellStyle }}">{{ \App\Support\TransactionItemViewOptions::skuColumnValue($item, $itemView) }}</td>
                @endif
                @if($itemView['showName'])
                    <td style="{{ $cellStyle }}">
                        @if($plainPrint)
                            {{ $item?->getItemName() ?? '-' }}
                            @if($detail->notes)
                                <br><em>{{ $detail->notes }}</em>
                            @endif
                        @else
                            <div style="font-weight:bold;">{{ $item?->getItemName() ?? '-' }}</div>
                            @if($item?->code)
                                <div style="margin-top:2px; font-family:monospace; font-size:10px; color:#666;">{{ $item->code }}</div>
                            @endif
                            @if($detail->notes)
                                <div style="margin-top:4px; font-size:11px; font-style:italic; color:#666;">{{ $detail->notes }}</div>
                            @endif
                        @endif
                    </td>
                @endif
                @if($itemView['showDescription'])
                    <td style="{{ $cellStyle }}">{{ $item?->description ?: '-' }}</td>
                @endif
                <td class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">{{ $fmt($detail->quantity) }}</td>
                <td class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">{{ $fmt($detail->price) }}</td>
                <td class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $cellStyle }}">
                    @if((float) $detail->discount > 0)
                        {{ $plainPrint ? $detail->discount : '-'.$fmt((float) $detail->discount).'%' }}
                    @else
                        -
                    @endif
                </td>
                <td class="{{ $plainPrint ? 'num' : '' }}" style="text-align:right;{{ $plainPrint ? '' : ' font-weight:bold;' }}{{ $cellStyle }}">{{ $fmt($detail->total) }}</td>
            </tr>
        @endforeach
    </tbody>
    @if(! ($forPdf ?? false) && ! ($plainPrint ?? false))
    <tfoot>
        <tr><td colspan="{{ $totalCols }}"><hr></td></tr>
        <tr>
            <td colspan="{{ $labelColspan }}" style="text-align:right;">Subtotal</td>
            <td style="text-align:right;">{{ $fmt($transaction->itemsSubtotalAmount()) }}</td>
        </tr>
        @if((float) $transaction->discount > 0)
            <tr>
                <td colspan="{{ $labelColspan }}" style="text-align:right;">Discount</td>
                <td style="text-align:right;">-{{ $fmt($transaction->displayInvoiceDiscountAmount()) }}</td>
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
            <td style="text-align:right;"><strong>{{ $fmt($transaction->displayGrandTotal()) }}</strong></td>
        </tr>
    </tfoot>
    @endif
</table>
