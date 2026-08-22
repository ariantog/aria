@extends('layouts.app')

@section('title', 'Invoice ' . $invoice->number)

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Invoice Maker', 'href' => route('invoice-maker.index')],
    ['title' => $invoice->number, 'href' => route('invoice-maker.show', $invoice)],
];
$fmt = fn ($n) => format_currency($n);
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div class="flex items-center gap-3">
            <a href="{{ route('invoice-maker.index') }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Invoice Detail</h1>
                <p class="text-sm text-gray-500">{{ $invoice->number }} · {{ $invoice->formattedDate() }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative" x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1.5 rounded-md border border-blue-300 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Invoice PDF
                    <svg class="h-3.5 w-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak @click.away="open = false"
                     class="absolute right-0 top-full z-30 mt-1 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                    @if($hasInvoicePdf)
                    <a href="{{ $invoicePdfUrl }}" target="_blank" rel="noopener"
                       class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">View PDF</a>
                    <a href="{{ $invoicePdfDownloadUrl }}" data-testid="download-invoice-pdf"
                       class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Download PDF</a>
                    <form method="POST" action="{{ route('invoice-maker.pdf.store', $invoice) }}">
                        @csrf
                        <button type="submit" data-testid="regenerate-invoice-pdf"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Regenerate PDF</button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('invoice-maker.pdf.store', $invoice) }}">
                        @csrf
                        <button type="submit" data-testid="generate-invoice-pdf"
                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Generate PDF</button>
                    </form>
                    @endif
                </div>
            </div>

            @if($can['edit'])
            <a href="{{ route('invoice-maker.edit', $invoice) }}"
               class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit</a>
            @endif

            @if($can['delete'])
            <form method="POST" action="{{ route('invoice-maker.destroy', $invoice) }}"
                  onsubmit="return confirm('Delete this invoice?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100">Delete</button>
            </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div><dt class="text-gray-500">Recipient</dt><dd class="font-medium whitespace-pre-line text-gray-900">{{ $invoice->recipient }}</dd></div>
                    <div><dt class="text-gray-500">Template</dt><dd class="font-medium text-gray-900">{{ \App\Models\StandaloneInvoice::TEMPLATES[$invoice->template] ?? $invoice->template }}</dd></div>
                    <div><dt class="text-gray-500">From</dt><dd class="font-medium text-gray-900">{{ $invoice->sender?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">Created by</dt><dd class="font-medium text-gray-900">{{ $invoice->user?->name ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Item</th>
                            <th class="px-4 py-3 text-right font-semibold">Qty</th>
                            <th class="px-4 py-3 text-right font-semibold">Price</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($invoice->lines as $line)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $line->description }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">{{ format_amount($line->quantity) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-gray-700">{{ $fmt($line->price) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-medium text-gray-900">{{ $fmt($line->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 bg-gray-50">
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">TOTAL</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-gray-900">{{ format_amount($invoice->total_qty) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">SUB TOTAL</td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold text-gray-900">{{ $fmt($invoice->subtotal) }}</td>
                        </tr>
                        @if($invoice->hasDownPayment())
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-semibold text-red-600">DP</td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold text-red-600">{{ $fmt($invoice->dp_amount) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right font-semibold text-gray-900">TOTAL</td>
                            <td class="px-4 py-3 text-right font-mono text-lg font-bold text-gray-900">{{ $fmt($invoice->balanceDue()) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
                <h3 class="mb-2 font-semibold text-gray-900">Terms of Payment</h3>
                <ul class="list-disc space-y-1 pl-5 text-gray-700">
                    @foreach(app(\App\Services\InvoiceMakerSettingsService::class)->termsBullets($invoice->terms_of_payment) as $bullet)
                        <li>{{ $bullet }}</li>
                    @endforeach
                </ul>
            </div>
            @php $payTo = app(\App\Services\InvoiceMakerSettingsService::class)->parsePayTo($invoice->pay_to); @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
                <h3 class="mb-2 font-semibold text-gray-900">Pay To</h3>
                <dl class="space-y-1 text-gray-700">
                    <div><dt class="text-gray-500">Bank</dt><dd class="font-medium">{{ $payTo['bank'] ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Account No</dt><dd class="font-mono font-medium">{{ $payTo['account_number'] ?: '—' }}</dd></div>
                    <div><dt class="text-gray-500">Account Name</dt><dd class="font-medium">{{ $payTo['account_name'] ?: '—' }}</dd></div>
                </dl>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
                <h3 class="mb-2 font-semibold text-gray-900">Signatory</h3>
                <p class="font-medium text-gray-900">{{ $invoice->signatory_name ?: '—' }}</p>
            </div>
            @if($invoice->notes)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-sm">
                <h3 class="mb-2 font-semibold text-gray-900">Notes</h3>
                <p class="text-gray-700 whitespace-pre-line">{{ $invoice->notes }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
