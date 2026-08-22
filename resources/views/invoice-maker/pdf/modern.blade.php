<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        .banner { background: #1d4ed8; color: #fff; padding: 20px 24px; }
        .banner-inner { display: table; width: 100%; }
        .banner-logo, .banner-info { display: table-cell; vertical-align: middle; }
        .banner-logo { width: 140px; }
        .banner-logo img { max-width: 120px; max-height: 60px; }
        .banner h1 { margin: 0; font-size: 22px; }
        .banner .sub { margin-top: 4px; font-size: 10px; opacity: 0.9; white-space: pre-line; }
        .content { padding: 24px; }
        .meta { display: table; width: 100%; margin-bottom: 16px; }
        .meta-left, .meta-right { display: table-cell; vertical-align: top; width: 50%; }
        .meta-right { text-align: right; }
        .pill { display: inline-block; background: #dbeafe; color: #1d4ed8; padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #eff6ff; color: #1e40af; padding: 8px; text-align: left; border-bottom: 2px solid #bfdbfe; }
        table.items td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        table.items .right { text-align: right; }
        table.items tfoot td { font-weight: bold; background: #f8fafc; }
        .bottom { display: table; width: 100%; margin-top: 20px; }
        .bottom-left, .bottom-right { display: table-cell; vertical-align: top; width: 50%; }
        .bottom-right { text-align: right; }
        .box { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; font-size: 10px; }
        .sign-img img { max-height: 55px; }
    </style>
</head>
<body>
    <div class="banner">
        <div class="banner-inner">
            <div class="banner-logo">
                @if($branding['logo_path'])
                    <img src="{{ $branding['logo_path'] }}" alt="">
                @endif
            </div>
            <div class="banner-info">
                <h1>{{ $branding['company_name'] }}</h1>
                <div class="sub">{{ $branding['address'] }}@if($branding['phone']) · {{ $branding['phone'] }}@endif</div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="meta">
            <div class="meta-left">
                <div class="pill">INVOICE</div>
                <div style="margin-top:8px;"><strong>{{ $invoice->number }}</strong></div>
                <div>{{ $invoice->formattedDate() }}</div>
            </div>
            <div class="meta-right">
                <div style="font-size:10px;color:#6b7280;">Bill To</div>
                <div style="font-size:14px;font-weight:bold;white-space:pre-line;">{{ $invoice->recipient }}</div>
            </div>
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
            <tfoot>
                <tr>
                    <td colspan="3" class="right">Subtotal</td>
                    <td class="right">{{ format_currency($invoice->subtotal) }}</td>
                </tr>
                @if($invoice->hasDownPayment())
                <tr>
                    <td colspan="3" class="right" style="color:#dc2626;">DP</td>
                    <td class="right" style="color:#dc2626;">{{ format_currency($invoice->dp_amount) }}</td>
                </tr>
                @endif
                <tr>
                    <td colspan="3" class="right">Grand Total</td>
                    <td class="right">{{ format_currency($invoice->balanceDue()) }}</td>
                </tr>
            </tfoot>
        </table>

        <div class="bottom">
            <div class="bottom-left">
                <div class="box">
                    <strong>Pay To</strong><br>
                    {{ $payToParsed['bank'] }}<br>
                    {{ $payToParsed['account_number'] }}<br>
                    {{ $payToParsed['account_name'] }}
                </div>
                @if(count($termsBullets))
                <div class="box" style="margin-top:10px;">
                    <strong>Terms</strong>
                    <ul style="margin:6px 0 0 16px;padding:0;">
                        @foreach($termsBullets as $bullet)<li>{{ $bullet }}</li>@endforeach
                    </ul>
                </div>
                @endif
            </div>
            <div class="bottom-right">
                <div>Dengan hormat,</div>
                @if($signaturePath)<div class="sign-img"><img src="{{ $signaturePath }}" alt=""></div>@endif
                <div style="font-weight:bold;">{{ $signatoryName }}</div>
            </div>
        </div>
    </div>
</body>
</html>
