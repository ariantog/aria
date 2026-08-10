<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt #{{ $transaction->invoice_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/receipt.css') }}">
</head>
<body onload="window.print()">
<div class="receipt">
    <div class="center title-main">{{ $branding['company_name'] }}</div>
    <div class="center title-sub">{{ $branding['address'] }}</div>
    @if($branding['phone'])
    <div class="center title-sub">Tel: {{ $branding['phone'] }}</div>
    @endif
    <br>
    <div class="center invoice-label">Retail Invoice</div>
    <br>
    <div>Date : {{ $transaction->date?->format('d/m/Y') }}</div>
    <div>Bill No: {{ $transaction->invoice_number }}</div>
    <hr>

    <div class="receipt-line">Item              Qty      Amt</div>
    <hr>

    @php
        $subtotal = 0;
        $subq = 0;
        $fmt = fn ($n) => number_format((float) $n, 0, ',', '.');
    @endphp

    @foreach($transaction->details as $detail)
        @php
            $itemName = $detail->item?->getItemName() ?? '-';
            $item = str_pad(substr($itemName, 0, 18), 18);
            $qty = str_pad((string) (int) $detail->quantity, 3, ' ', STR_PAD_LEFT);
            $amt = str_pad($fmt($detail->total), 9, ' ', STR_PAD_LEFT);
            $subtotal += (float) $detail->total;
            $subq += (int) $detail->quantity;
        @endphp
        <div class="receipt-line">{{ $item }}{{ $qty }}{{ $amt }}</div>
    @endforeach

    <hr>
    @php
        $stText = str_pad('SubTotal', 18);
        $stQty = str_pad((string) $subq, 3, ' ', STR_PAD_LEFT);
        $stAmt = str_pad($fmt($subtotal), 9, ' ', STR_PAD_LEFT);
        $discount = max(0, $subtotal - abs((float) $transaction->grand_total));
    @endphp
    <div class="receipt-line">{{ $stText }}{{ $stQty }}{{ $stAmt }}</div>
    <hr>

    <div>Discount : {{ $fmt($discount) }}</div>
    <div><strong>TOTAL : {{ $fmt(abs($transaction->grand_total)) }}</strong></div>
    <br>
    <div class="center thankyou">@corenationactive 082244226656</div>
</div>
</body>
</html>
