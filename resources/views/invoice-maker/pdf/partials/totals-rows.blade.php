{{-- Shared subtotal / discount / DP / grand-total rows for classic & modern PDF templates. --}}
@php
    $grandTotal = max(0, $invoice->balanceDue() - $invoice->discountAmount());
@endphp
<tr>
    <td colspan="3" class="{{ $alignClass ?? 'money' }}" style="text-align:right;">{{ $subtotalLabel ?? 'SUB TOTAL' }}</td>
    <td class="{{ $moneyClass ?? 'money' }}">{{ format_currency($invoice->subtotal) }}</td>
</tr>
@if($invoice->discountAmount() > 0)
<tr>
    <td colspan="3" class="{{ $alignClass ?? 'money' }}" style="text-align:right;">{{ $discountLabel ?? 'DISCOUNT' }}</td>
    <td class="{{ $moneyClass ?? 'money' }}">{{ format_currency($invoice->discountAmount()) }}</td>
</tr>
@endif
@if($invoice->hasDownPayment())
<tr>
    <td colspan="3" class="{{ $alignClass ?? 'money' }} {{ $dpClass ?? 'dp' }}" style="text-align:right;{{ $dpStyle ?? '' }}">{{ $dpLabel ?? 'DP' }}</td>
    <td class="{{ $moneyClass ?? 'money' }} {{ $dpClass ?? 'dp' }}" style="{{ $dpStyle ?? '' }}">{{ format_currency($invoice->dp_amount) }}</td>
</tr>
@endif
<tr>
    <td colspan="3" class="{{ $alignClass ?? 'money' }}" style="text-align:right;">{{ $totalLabel ?? 'TOTAL' }}</td>
    <td class="{{ $moneyClass ?? 'money' }}">{{ format_currency($grandTotal) }}</td>
</tr>
