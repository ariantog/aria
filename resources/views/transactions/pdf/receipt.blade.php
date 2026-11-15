<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $transaction->invoice }}</title>
    <style>
        @page {
            size: 58mm auto;
            margin: 0;
        }

        body {
            margin: 0;
            padding: 2mm;
            width: 58mm;
            font-family: DejaVu Sans Mono, monospace;
            font-size: 10px;
            line-height: 1.35;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .receipt-line {
            font-family: DejaVu Sans Mono, monospace;
            white-space: pre;
            font-size: 9px;
        }

        hr {
            border: 0;
            border-top: 1px solid #000;
            margin: 4px 0;
        }

        .bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="center bold">{{ $branding['company_name'] }}</div>
@if($branding['address'])
    <div class="center">{{ $branding['address'] }}</div>
@endif
@if($branding['phone'])
    <div class="center">{{ $branding['phone'] }}</div>
@endif
<br>
<div class="center">Retail Invoice</div>
<br>
<div>Date : {{ $transaction->date?->format('d/m/Y') }}</div>
<div>Invoice: {{ $transaction->invoice ?: $transaction->id }}</div>
<div>{{ $transaction->sender?->name }}</div>
<hr>
<div class="receipt-line">Item              Qty      Amt</div>
<hr>
@php
    $subtotal = 0.0;
    $subQty = 0.0;
@endphp
@foreach ($transaction->details as $detail)
    @php
        $lineTotal = abs((float) $detail->total);
        $name = $detail->item?->getItemName() ?? '-';
        $itemCol = str_pad(mb_substr($name, 0, 18), 18);
        $qtyCol = str_pad(number_format((float) $detail->quantity, 0, ',', '.'), 3, ' ', STR_PAD_LEFT);
        $amtCol = str_pad(number_format($lineTotal, 0, ',', '.'), 9, ' ', STR_PAD_LEFT);
        $subtotal += $lineTotal;
        $subQty += (float) $detail->quantity;
    @endphp
    <div class="receipt-line">{{ $itemCol }}{{ $qtyCol }}{{ $amtCol }}</div>
@endforeach
<hr>
@php
    $stText = str_pad('SubTotal', 18);
    $stQty = str_pad(number_format($subQty, 0, ',', '.'), 3, ' ', STR_PAD_LEFT);
    $stAmt = str_pad(number_format($subtotal, 0, ',', '.'), 9, ' ', STR_PAD_LEFT);
@endphp
<div class="receipt-line">{{ $stText }}{{ $stQty }}{{ $stAmt }}</div>
@if((float) $transaction->discount > 0)
    <div>Disc {{ $transaction->discount }}%</div>
@endif
@if((float) $transaction->adjustment != 0)
    <div>Adj: {{ number_format((float) $transaction->adjustment, 0, ',', '.') }}</div>
@endif
@if((float) $transaction->ppn > 0)
    <div>PPN: {{ number_format((float) $transaction->ppn, 0, ',', '.') }}</div>
@endif
<hr>
<div class="bold">TOTAL: Rp{{ number_format($transaction->displayGrandTotal(), 0, ',', '.') }}</div>
<br>
<div class="center">Terima kasih</div>
</body>
</html>