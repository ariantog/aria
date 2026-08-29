@php
    $fmt = fn ($n) => format_amount($n);
    $viewColumns = $viewColumns ?? \App\Support\TransactionItemViewColumns::defaults();
    $itemColumnCount = \App\Support\TransactionItemViewColumns::visibleItemColumnCount($viewColumns);
    $tableColumnCount = $itemColumnCount + 3;
    $footerLabelColspan = max(1, $tableColumnCount - 1);
@endphp

<div id="invoice">
    <h5>{{ $branding['company_name'] }}</h5>
    <div style="white-space: pre-line;">{{ $branding['address'] }}</div>
    @if($branding['phone'])
    <div>Tel: {{ $branding['phone'] }}</div>
    @endif
    <br>
    <h5>{{ $typeLabel }} Invoice</h5>
    <table class="invoice" style="margin-top:8px;">
        <tr>
            <td>Invoice</td>
            <td>: {{ $transaction->invoice }}</td>
            <td>Date</td>
            <td>: {{ $transaction->date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>From</td>
            <td>: {{ $transaction->sender?->name ?? '-' }}</td>
            <td>To</td>
            <td>: {{ $transaction->receiver?->name ?? '-' }}</td>
        </tr>
    </table>

    <table class="invoice" style="margin-top:12px;">
        <thead>
            <tr>
                @if($viewColumns['image'])
                <th style="text-align:center;">Img</th>
                @endif
                @if($viewColumns['barcode'])
                <th style="text-align:left;">Barcode</th>
                @endif
                @if($viewColumns['sku'])
                <th style="text-align:left;">SKU</th>
                @endif
                @if($viewColumns['name'])
                <th style="text-align:left;">Item</th>
                @endif
                @if($viewColumns['description'])
                <th style="text-align:left;">Desc</th>
                @endif
                <th style="text-align:right;">Qty</th>
                <th style="text-align:right;">Price</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->details as $detail)
            @php $item = $detail->item; @endphp
            <tr>
                @if($viewColumns['image'])
                <td style="text-align:center;">
                    @if($item?->image_url)
                        <img src="{{ $item->image_url }}" alt="{{ $item->getItemName() }}" style="max-height:40px;max-width:40px;">
                    @else
                        -
                    @endif
                </td>
                @endif
                @if($viewColumns['barcode'])
                <td>{{ $item?->id ?? '-' }}</td>
                @endif
                @if($viewColumns['sku'])
                <td>{{ $item?->code ?: '-' }}</td>
                @endif
                @if($viewColumns['name'])
                <td>{{ $item?->getItemName() ?? '-' }}</td>
                @endif
                @if($viewColumns['description'])
                <td>{{ $item?->description ?: '-' }}</td>
                @endif
                <td style="text-align:right;">{{ $fmt($detail->quantity) }}</td>
                <td style="text-align:right;">{{ $fmt($detail->price) }}</td>
                <td style="text-align:right;">{{ $fmt($detail->total) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="{{ $tableColumnCount }}"><hr></td></tr>
            <tr>
                <td colspan="{{ $footerLabelColspan }}" style="text-align:right;">Subtotal</td>
                <td style="text-align:right;">{{ $fmt($transaction->total) }}</td>
            </tr>
            @if((float) $transaction->discount > 0)
            <tr>
                <td colspan="{{ $footerLabelColspan }}" style="text-align:right;">Discount</td>
                <td style="text-align:right;">-{{ $fmt($transaction->discount) }}</td>
            </tr>
            @endif
            @if((float) $transaction->adjustment != 0)
            <tr>
                <td colspan="{{ $footerLabelColspan }}" style="text-align:right;">Adjustment</td>
                <td style="text-align:right;">{{ $fmt($transaction->adjustment) }}</td>
            </tr>
            @endif
            @if((float) $transaction->ppn > 0)
            <tr>
                <td colspan="{{ $footerLabelColspan }}" style="text-align:right;">PPN</td>
                <td style="text-align:right;">{{ $fmt($transaction->ppn) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="{{ $footerLabelColspan }}" style="text-align:right;"><strong>Grand Total</strong></td>
                <td style="text-align:right;"><strong>{{ $fmt(abs($transaction->real_total)) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if($transaction->notes)
    <p style="margin-top:12px;"><em>Note: {{ $transaction->notes }}</em></p>
    @endif
</div>
