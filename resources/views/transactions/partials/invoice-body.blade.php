@php
    $fmt = fn ($n) => format_amount($n);
    $itemView = $itemView ?? \App\Support\TransactionItemViewOptions::defaults();
@endphp

<div id="invoice">
    <h5>{{ $branding['company_name'] }}</h5>
    <div style="white-space: pre-line;">{{ $branding['address'] }}</div>
    @if($branding['phone'])
    <div>Tel: {{ $branding['phone'] }}</div>
    @endif
    <br>
    <h5>{{ $typeLabel }} Invoice</h5>
    <table class="invoice" style="margin-top:8px;">
        <tr>
            <td>Invoice</td>
            <td>: {{ $transaction->invoice }}</td>
            <td>Date</td>
            <td>: {{ $transaction->date?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>From</td>
            <td>: {{ $transaction->sender?->name ?? '-' }}</td>
            <td>To</td>
            <td>: {{ $transaction->receiver?->name ?? '-' }}</td>
        </tr>
    </table>

    @include('transactions.partials.invoice-items-table', [
        'transaction' => $transaction,
        'itemView' => $itemView,
        'forPdf' => false,
        'fmt' => $fmt,
    ])

    @if($transaction->notes)
    <p style="margin-top:12px;"><em>Note: {{ $transaction->notes }}</em></p>
    @endif

    @include('transactions.partials.invoice-signature-block', ['transaction' => $transaction])
</div>
