@php
    $status = $status ?? \App\Models\StandaloneInvoice::STATUS_UNPAID;
    $label = $label ?? (\App\Models\StandaloneInvoice::STATUSES[$status] ?? $status);
    $classes = match ($status) {
        \App\Models\StandaloneInvoice::STATUS_PAID => 'bg-green-100 text-green-800',
        \App\Models\StandaloneInvoice::STATUS_PARTIAL => 'bg-amber-100 text-amber-800',
        default => 'bg-gray-100 text-gray-700',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $classes }}">{{ $label }}</span>
