@php
    $fmt = fn ($n) => format_amount($n);
    $itemView = $itemView ?? \App\Support\TransactionItemViewOptions::defaults();
@endphp

<div id="invoice">
    <h5 class="sh">{{ $branding['company_name'] }}</h5>
    <div class="sh" style="white-space: pre-line;">{{ $branding['address'] }}</div>
    @if($branding['phone'])
        <div class="sh">Tel: {{ $branding['phone'] }}</div>
    @endif
    <br>

    <table width="100%" border="0">
        <tr>
            <td>
                <strong>{{ $transaction->invoice }}</strong><br>
                <table>
                    @if($transaction->sender)
                        <tr>
                            <td><strong>Sender:</strong></td>
                            <td>{{ $transaction->sender->name }}</td>
                        </tr>
                    @endif
                    @if($transaction->receiver)
                        <tr>
                            <td><strong>Receiver:</strong></td>
                            <td>{{ $transaction->receiver->name }}</td>
                        </tr>
                    @endif
                </table>
            </td>
            <td>
                <table>
                    <tbody>
                        <tr><td>Date</td><td>{{ $transaction->date?->format('d/m/Y') }}</td></tr>
                        <tr><td>Discount</td><td>{{ $transaction->discount }}%</td></tr>
                        <tr><td>Adjustment</td><td>{{ $fmt($transaction->adjustment) }}</td></tr>
                        <tr><td>Total</td><td>{{ $fmt($transaction->displayGrandTotal()) }}</td></tr>
                        <tr><td>Items</td><td>{{ $fmt($transaction->total_items) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    @if($transaction->details->isNotEmpty())
        <hr>
        @include('transactions.partials.invoice-items-table', [
            'transaction' => $transaction,
            'itemView' => $itemView,
            'forPdf' => false,
            'plainPrint' => true,
            'fmt' => $fmt,
        ])
    @endif

    <hr>

    @if($transaction->notes)
        <p><em>Note: {{ $transaction->notes }}</em></p>
    @endif

    @include('transactions.partials.invoice-signature-block', ['transaction' => $transaction])
</div>
