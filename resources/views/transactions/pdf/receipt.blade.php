<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $transaction->invoice }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 20mm;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header, .footer {
            width: 100%;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
        }

        .section {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        th, td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: left;
            word-wrap: break-word;
        }

        tr, td, th {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        .text-right {
            text-align: right;
        }

        .bold {
            font-weight: bold;
        }

        .totals {
            width: 100%;
            margin-top: 20px;
        }

        .totals td {
            padding: 5px;
        }

        .totals .label {
            text-align: right;
            font-weight: bold;
        }

        .footer-note {
            margin-top: 30px;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <table style="border: none;">
                <tr>
                    <td style="border: none; white-space: nowrap; width: 30px;">
                        @if($branding['logo_path'])
                            <img src="{{ $branding['logo_path'] }}" style="width: auto;" alt="CoreNation Active">
                        @endif
                    </td>
                    <td style="border: none;">
                        <h2 style="margin: 0;">CoreNation Active</h2>
                    </td>
                </tr>
            </table>
        </div>
        <p><strong>Invoice : </strong>{{ $transaction->invoice }}</p>
        <p><strong>Date: </strong>{{ $transaction->date?->format('d M Y') }}</p>
    </div>

    <div class="section">
        <table style="border: none;">
            <tr>
                <td style="border: none;">
                    {{ $transaction->sender?->name }}<br>
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Name</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Amt</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaction->details as $detail)
                <tr>
                    <td>{{ $detail->item?->getItemName() ?? '-' }}<br>{{ $detail->item?->getItemCode() ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) $detail->quantity, 0, ',', '.') }}</td>
                    <td class="text-right">
                        Rp{{ number_format(abs((float) $detail->price), 0, ',', '.') }}
                        @if((float) $detail->discount > 0)
                            <br>({{ $detail->discount }}%)
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Total Items:</td>
            <td class="text-right">{{ $transaction->total_items }}</td>
        </tr>
        @if((float) $transaction->discount > 0)
            <tr>
                <td class="label">Discount:</td>
                <td class="text-right">{{ $transaction->discount }}%</td>
            </tr>
        @endif
        @if((float) $transaction->adjustment != 0)
            <tr>
                <td class="label">Adjustment:</td>
                <td class="text-right">(Rp{{ number_format(abs((float) $transaction->adjustment), 0, ',', '.') }})</td>
            </tr>
        @endif
        <tr>
            <td class="label"><strong>Grand Total:</strong></td>
            <td class="text-right"><strong>Rp{{ number_format(abs((float) $transaction->real_total), 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <div class="footer-note">
        @corenationactive 082244226656
    </div>
</body>
</html>
