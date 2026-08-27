<!DOCTYPE html>
<html lang="en">
<head>
<title>CORENATION</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="{{ asset('css/receipt.css') }}">
</head>
<body onload="window.print()">
<div class="receipt">
    <div class="center">CORENATION</div>
    <div class="center">CILANDAK TOWN SQUARE no.171</div>
    <div class="center">FX SUDIRMAN lt.4</div>
    <div class="center">BSD MAGGIORE GRANDE G50</div>
    <br>
    <div class="center">Retail Invoice</div>
    <br>
    <div>Date : {{ $transaction->date?->format('d/m/Y') }}</div>
    <div>Bill No: {{ $transaction->invoice }}</div>
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
            $itemCode = $detail->item?->getItemCode() ?? '';
            $item = str_pad(substr($itemName, 0, 18), 18);
            $qty = str_pad((string) (int) $detail->quantity, 3, ' ', STR_PAD_LEFT);
            $amt = str_pad($fmt($detail->total), 9, ' ', STR_PAD_LEFT);
            $subtotal += (float) $detail->total;
            $subq += (int) $detail->quantity;
        @endphp
        <div class="receipt-line">{{ $item }}{{ $qty }}{{ $amt }}</div>
        @if($itemCode !== '')
            <div class="receipt-line">{{ str_pad(substr($itemCode, 0, 18), 18) }}</div>
        @endif
    @endforeach

    <hr>
    @php
        $stText = str_pad('SubTotal', 18);
        $stQty = str_pad((string) $subq, 3, ' ', STR_PAD_LEFT);
        $stAmt = str_pad($fmt($subtotal), 9, ' ', STR_PAD_LEFT);
        $discount = abs((float) $transaction->real_total) - $subtotal;
    @endphp
    <div class="receipt-line">{{ $stText }}{{ $stQty }}{{ $stAmt }}</div>
    <hr>

    <div>Discount : {{ $fmt($discount) }}</div>
    <div><strong>TOTAL : {{ $fmt(abs((float) $transaction->real_total)) }}</strong></div>
    <br>
    <div class="center">@corenationactive 082244226656</div>
</div>
</body>
</html>
