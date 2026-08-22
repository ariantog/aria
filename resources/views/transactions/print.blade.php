<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Invoice #{{ $transaction->invoice }}</title>
    <link rel="stylesheet" href="{{ asset('css/print.css') }}" media="print">
    <link rel="stylesheet" href="{{ asset('css/print.css') }}">
</head>
<body onload="window.print()">
    @include('transactions.partials.invoice-body', ['transaction' => $transaction, 'typeLabel' => $typeLabel, 'branding' => $branding])
</body>
</html>
