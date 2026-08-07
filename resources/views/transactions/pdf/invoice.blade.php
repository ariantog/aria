<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $transaction->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        .meta td { border: none; padding: 2px 8px 2px 0; }
        .right { text-align: right; }
        .totals td { border: none; }
    </style>
</head>
<body>
    <h1>CORENATION</h1>
    <div>CILANDAK TOWN SQUARE no.171</div>

    <h2>{{ $typeLabel }} Invoice #{{ $transaction->invoice_number }}</h2>

    <table class="meta">
        <tr>
            <td><strong>Date</strong></td>
            <td>{{ $transaction->date?->format('d/m/Y') }}</td>
            <td><strong>From</strong></td>
            <td>{{ $transaction->sender?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>To</strong></td>
            <td>{{ $transaction->receiver?->name ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->details as $detail)
            <tr>
                <td>{{ $detail->item?->getItemName() ?? '-' }}</td>
                <td class="right">{{ number_format((float) $detail->quantity, 0, ',', '.') }}</td>
                <td class="right">{{ number_format((float) $detail->price, 0, ',', '.') }}</td>
                <td class="right">{{ number_format((float) $detail->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="width: 280px; margin-left: auto; margin-top: 12px;">
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ number_format((float) $transaction->total, 0, ',', '.') }}</td>
        </tr>
        @if((float) $transaction->discount > 0)
        <tr>
            <td>Discount</td>
            <td class="right">-{{ number_format((float) $transaction->discount, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if((float) $transaction->tax_amount > 0)
        <tr>
            <td>PPN</td>
            <td class="right">{{ number_format((float) $transaction->tax_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr>
            <td><strong>Grand Total</strong></td>
            <td class="right"><strong>{{ number_format(abs((float) $transaction->grand_total), 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    @if($transaction->notes)
    <p style="margin-top: 16px;"><em>{{ $transaction->notes }}</em></p>
    @endif
</body>
</html>
