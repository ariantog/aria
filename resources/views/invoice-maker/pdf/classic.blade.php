<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        .logo img { max-width: 180px; max-height: 70px; }
        .company { margin-top: 8px; line-height: 1.45; font-size: 10px; }
        .title { text-align: center; font-size: 18px; font-weight: bold; text-decoration: underline; margin: 20px 0 16px; letter-spacing: 1px; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { vertical-align: top; padding: 2px 0; }
        .meta .label { width: 70px; font-weight: bold; }
        .recipient { text-align: right; }
        .recipient .name { font-weight: bold; font-size: 12px; margin-top: 4px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #333; padding: 6px 8px; }
        table.items th { font-weight: bold; text-align: center; }
        table.items td.item { text-align: center; font-weight: bold; }
        table.items td.qty { text-align: center; }
        table.items td.money { text-align: right; }
        table.items tfoot td { font-weight: bold; }
        .footer { width: 100%; margin-top: 24px; }
        .footer td { vertical-align: top; width: 50%; }
        .payto { font-size: 10px; line-height: 1.5; }
        .payto .label { font-weight: bold; }
        .signature { text-align: right; font-size: 10px; }
        .signature .sign-img { height: 60px; margin: 8px 0; }
        .signature .name { font-weight: bold; text-decoration: underline; font-size: 11px; }
        .terms { margin-top: 16px; font-size: 10px; }
        .terms ul { margin: 4px 0 0 16px; padding: 0; }
    </style>
</head>
<body>
    <div class="logo">
        @if($branding['logo_path'])
            <img src="{{ $branding['logo_path'] }}" alt="{{ $branding['company_name'] }}">
        @else
            <strong style="font-size:14px;">{{ $branding['company_name'] }}</strong>
        @endif
    </div>
    <div class="company">
        <div style="white-space: pre-line;">{{ $branding['address'] }}</div>
        @if($branding['phone'])
            <div>T: {{ $branding['phone'] }}</div>
        @endif
    </div>

    <div class="title">INVOICE</div>

    <table class="meta">
        <tr>
            <td style="width:50%;">
                <table>
                    <tr><td class="label">Nomor</td><td>: {{ $invoice->number }}</td></tr>
                    <tr><td class="label">Tanggal</td><td>: {{ $invoice->formattedDate() }}</td></tr>
                </table>
            </td>
            <td class="recipient">
                <div>Kepada</div>
                <div class="name" style="white-space: pre-line;">{{ $invoice->recipient }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:45%;">ITEM</th>
                <th style="width:15%;">QTY</th>
                <th style="width:20%;">PRICE</th>
                <th style="width:20%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
            <tr>
                <td class="item">{{ $line->description }}</td>
                <td class="qty">{{ format_amount($line->quantity) }}</td>
                <td class="money">{{ format_currency($line->price) }}</td>
                <td class="money">{{ format_currency($line->total) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td class="item">TOTAL</td>
                <td class="qty">{{ format_amount($invoice->total_qty) }}</td>
                <td class="money">SUB TOTAL</td>
                <td class="money">{{ format_currency($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="money" style="text-align:right;">TOTAL</td>
                <td class="money">{{ format_currency($invoice->subtotal) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="footer">
        <tr>
            <td class="payto">
                @if($payToParsed['bank'] || $payToParsed['account_number'] || $payToParsed['account_name'])
                    <div><span class="label">BANK</span> : {{ $payToParsed['bank'] }}</div>
                    <div><span class="label">Account No</span> : {{ $payToParsed['account_number'] }}</div>
                    <div><span class="label">Account Name</span> : {{ $payToParsed['account_name'] }}</div>
                @endif
            </td>
            <td class="signature">
                <div>Dengan hormat,</div>
                <div>Mengetahui</div>
                @if($signaturePath)
                    <div class="sign-img"><img src="{{ $signaturePath }}" alt="Signature" style="max-height:60px;"></div>
                @else
                    <div style="height:60px;"></div>
                @endif
                <div class="name">{{ $signatoryName }}</div>
            </td>
        </tr>
    </table>

    @if(count($termsBullets))
    <div class="terms">
        <strong>Terms of Payment:</strong>
        <ul>
            @foreach($termsBullets as $bullet)
                <li>{{ $bullet }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @if($invoice->notes)
    <div class="terms"><em>{{ $invoice->notes }}</em></div>
    @endif
</body>
</html>
