@extends('layouts.app')

@section('title', 'Invoice Maker Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Invoice Maker', 'href' => route('invoice-maker.index')],
    ['title' => 'Settings', 'href' => route('invoice-maker.settings.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">Invoice Maker Settings</h2>
            <p class="mt-0.5 text-sm text-gray-500">Manage payment terms, pay-to bank details, signatory, signature, and template presets used when creating invoices.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('invoice-maker.index') }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back to Invoices</a>
            <a href="{{ route('invoice-maker.settings.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Preset
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Preset</th>
                    <th class="px-4 py-3 font-semibold">Template</th>
                    <th class="px-4 py-3 font-semibold">Signatory</th>
                    <th class="px-4 py-3 font-semibold">Signature</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($presets as $preset)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $preset['name'] }}</div>
                        @if($defaultPresetId === $preset['id'])
                            <span class="mt-1 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Default</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ \App\Models\StandaloneInvoice::TEMPLATES[$preset['template']] ?? $preset['template'] }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $preset['signatory_name'] ?: '—' }}</td>
                    <td class="px-4 py-3">
                        @if($preset['signature_url'])
                            <img src="{{ $preset['signature_url'] }}" alt="Signature" class="h-10 w-auto object-contain">
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('invoice-maker.settings.edit', $preset['id']) }}" class="text-sm font-medium text-blue-700 hover:underline">Edit</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-12 text-center text-gray-500">No presets yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
