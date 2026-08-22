<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 32px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .muted { color: #666; font-size: 10px; }
        .row { width: 100%; margin: 16px 0; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border-bottom: 1px solid #ddd; padding: 6px 4px; }
        table.items th { text-align: left; font-size: 10px; text-transform: uppercase; color: #666; }
        .right { text-align: right; }
        .total { font-size: 13px; font-weight: bold; margin-top: 8px; text-align: right; }
        .cols { display: table; width: 100%; margin-top: 24px; }
        .col { display: table-cell; width: 50%; vertical-align: top; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $branding['company_name'] }}</h1>
    <div class="muted" style="white-space:pre-line;">{{ $branding['address'] }}</div>

    <div class="row">
        <strong>Invoice</strong> {{ $invoice->number }}<br>
        <span class="muted">{{ $invoice->formattedDate() }}</span>
    </div>

    <div class="row">
        <span class="muted">To</span><br>
        <strong style="white-space:pre-line;">{{ $invoice->recipient }}</strong>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Price</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
            <tr>
                <td>{{ $line->description }}</td>
                <td class="right">{{ format_amount($line->quantity) }}</td>
                <td class="right">{{ format_currency($line->price) }}</td>
                <td class="right">{{ format_currency($line->total) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        @if($invoice->hasDownPayment())
            Subtotal {{ format_currency($invoice->subtotal) }} ·
            <span style="color:#dc2626;">DP {{ format_currency($invoice->dp_amount) }}</span> ·
            Total {{ format_currency($invoice->balanceDue()) }}
        @else
            Total {{ format_currency($invoice->subtotal) }}
        @endif
    </div>

    <div class="cols">
        <div class="col">
            @if($payToParsed['bank'])
                <strong>Pay to</strong><br>
                {{ $payToParsed['bank'] }} · {{ $payToParsed['account_number'] }}<br>
                {{ $payToParsed['account_name'] }}
            @endif
            @if(count($termsBullets))
                <br><br><strong>Terms</strong>
                <ul style="margin:4px 0 0 14px;padding:0;">
                    @foreach($termsBullets as $bullet)<li>{{ $bullet }}</li>@endforeach
                </ul>
            @endif
        </div>
        <div class="col right">
            {{ $signatoryName }}<br>
            @if($signaturePath)<img src="{{ $signaturePath }}" alt="" style="max-height:50px;margin-top:8px;">@endif
        </div>
    </div>
</body>
</html>
