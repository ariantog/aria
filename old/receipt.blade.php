<!DOCTYPE html>
<html lang="en">
<head>
<title>CORENATION</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* Thermal Printer Compatible CSS */
@page {
    size: 58mm auto;
    margin: 0;
}

body {
    margin: 0;
    padding: 2mm;
    width: 58mm;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
}

/* IMPORTANT: Use single words to avoid spacing issues */
.subtotal { /* NOT "Sub Total" */
    font-weight: bold;
}

/* Use monospace formatting for alignment */
.receipt-line {
    font-family: monospace;
    white-space: pre;
}

/* Simple center alignment */
.center { 
    text-align: center;
    margin: 0 auto;
}

/* Avoid complex table structures */
.item-row {
    clear: both;
    font-family: monospace;
}

/* Use floats instead of table cells */
.item-name {
    float: left;
    width: 50%;
}

.item-qty {
    float: left;
    width: 20%;
    text-align: right;
}

.item-amt {
    float: right;
    width: 30%;
    text-align: right;
}

/* Clear floats */
.clear {
    clear: both;
}

/* Simple line */
hr {
    border: 0;
    border-top: 1px solid #000;
    margin: 2px 0;
}

@media print {
    * {
        font-family: monospace !important;
    }
}
</style>
</head>
<body>
<!-- In your receipt.blade.php -->
<div class="receipt">
    <div class="center">CORENATION</div>
    <div class="center">CILANDAK TOWN SQUARE no.171</div>
    <div class="center">FX SUDIRMAN lt.4</div>
    <div class="center">BSD MAGGIORE GRANDE G50</div>
    <br>
    <div class="center">Retail Invoice</div>
    <br>
    <div>Date : {{ \Carbon\Carbon::parse($data->date)->format('d/m/Y') }}</div>
    <div>Bill No: {{ $data->id }}</div>
    <hr>
    
    <!-- Header with monospace alignment -->
    <div class="receipt-line">Item              Qty      Amt</div>
    <hr>
    
    @php
    $subtotal = 0;
    $subq = 0;
    @endphp
    
    @foreach($data->transactionDetail as $d)
    @php
        // Format with fixed width
        $item = str_pad(substr($d->item->getItemName(), 0, 18), 18);
        $qty = str_pad($d->quantity, 3, ' ', STR_PAD_LEFT);
        $amt = str_pad(number_format($d->total, 0, ',', '.'), 9, ' ', STR_PAD_LEFT);
        $subtotal += $d->total;
        $subq += $d->quantity;
    @endphp
    <div class="receipt-line">{{ $item }}{{ $qty }}{{ $amt }}</div>
    @endforeach
    
    <hr>
    @php
        $st_text = str_pad('SubTotal', 18);
        $st_qty = str_pad($subq, 3, ' ', STR_PAD_LEFT);
        $st_amt = str_pad(number_format($subtotal, 0, ',', '.'), 9, ' ', STR_PAD_LEFT);
    @endphp
    <div class="receipt-line">{{ $st_text }}{{ $st_qty }}{{ $st_amt }}</div>
    <hr>
    
    @php
    $discount = abs($data->total) - $subtotal;
    @endphp
    
    <div>Discount : {{ number_format($discount, 0, ',', '.') }}</div>
    <div><strong>TOTAL : {{ number_format(abs($data->total), 0, ',', '.') }}</strong></div>
    <br>
    <div class="center">@corenationactive 082244226656</div>
</div>
</body>
</html>
