@php
    $fmt = fn ($n) => format_amount($n);
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
                <th style="text-align:left;">Item</th>
                <th style="text-align:right;">Qty</th>
                <th style="text-align:right;">Price</th>
                <th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->details as $detail)
            <tr>
                <td>{{ $detail->item?->getItemName() ?? '-' }}</td>
                <td style="text-align:right;">{{ $fmt($detail->quantity) }}</td>
                <td style="text-align:right;">{{ $fmt($detail->price) }}</td>
                <td style="text-align:right;">{{ $fmt($detail->total) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="4"><hr></td></tr>
            <tr>
                <td colspan="3" style="text-align:right;">Subtotal</td>
                <td style="text-align:right;">{{ $fmt($transaction->total) }}</td>
            </tr>
            @if((float) $transaction->discount > 0)
            <tr>
                <td colspan="3" style="text-align:right;">Discount</td>
                <td style="text-align:right;">-{{ $fmt($transaction->discount) }}</td>
            </tr>
            @endif
            @if((float) $transaction->adjustment != 0)
            <tr>
                <td colspan="3" style="text-align:right;">Adjustment</td>
                <td style="text-align:right;">{{ $fmt($transaction->adjustment) }}</td>
            </tr>
            @endif
            @if((float) $transaction->ppn > 0)
            <tr>
                <td colspan="3" style="text-align:right;">PPN</td>
                <td style="text-align:right;">{{ $fmt($transaction->ppn) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="3" style="text-align:right;"><strong>Grand Total</strong></td>
                <td style="text-align:right;"><strong>{{ $fmt(abs($transaction->real_total)) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if($transaction->notes)
    <p style="margin-top:12px;"><em>Note: {{ $transaction->notes }}</em></p>
    @endif
</div>
