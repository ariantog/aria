<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $transaction->invoice }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 0; }
        .header { display: table; width: 100%; margin-bottom: 16px; }
        .header-logo, .header-info { display: table-cell; vertical-align: top; }
        .header-logo { width: 120px; padding-right: 16px; }
        .header-logo img { max-width: 110px; max-height: 70px; }
        h1 { font-size: 20px; margin: 0 0 4px; letter-spacing: 0.5px; }
        .address { font-size: 11px; line-height: 1.4; color: #333; white-space: pre-line; }
        .phone { font-size: 11px; margin-top: 4px; color: #333; }
        h2 { font-size: 14px; margin: 0 0 10px; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; }
        .meta td { border: none; padding: 2px 8px 2px 0; }
        .right { text-align: right; }
        .totals td { border: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">
            @if($branding['logo_path'])
                <img src="{{ $branding['logo_path'] }}" alt="{{ $branding['company_name'] }}">
            @endif
        </div>
        <div class="header-info">
            <h1>{{ $branding['company_name'] }}</h1>
            <div class="address">{{ $branding['address'] }}</div>
            @if($branding['phone'])
                <div class="phone">Tel: {{ $branding['phone'] }}</div>
            @endif
        </div>
    </div>

    <h2>{{ $typeLabel }} Invoice #{{ $transaction->invoice }}</h2>

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
        @if((float) $transaction->adjustment != 0)
        <tr>
            <td>Adjustment</td>
            <td class="right">{{ number_format((float) $transaction->adjustment, 0, ',', '.') }}</td>
        </tr>
        @endif
        @if((float) $transaction->ppn > 0)
        <tr>
            <td>PPN</td>
            <td class="right">{{ number_format((float) $transaction->ppn, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr>
            <td><strong>Grand Total</strong></td>
            <td class="right"><strong>{{ number_format(abs((float) $transaction->real_total), 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    @if($transaction->notes)
    <p style="margin-top: 16px;"><em>{{ $transaction->notes }}</em></p>
    @endif
</body>
</html>
