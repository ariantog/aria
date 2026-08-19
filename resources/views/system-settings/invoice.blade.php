@extends('layouts.app')

@section('title', 'Invoice Settings')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'System Settings', 'href' => route('system-settings.index')],
    ['title' => 'Invoice Settings', 'href' => route('invoice-settings.edit')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Invoice Settings</h2>
        <p class="mt-0.5 text-sm text-gray-500">Logo, default payment terms, pay-to bank details, signatory, and template for the Invoice Maker. Warehouse invoice headers still come from each warehouse's <strong>Invoice Header</strong> field on the addrbook form.</p>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    <div class="max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST" action="{{ route('invoice-settings.update') }}" enctype="multipart/form-data" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Branding</h3>
                <div class="mt-3 space-y-4">
                    <div>
                        <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                        @if($branding['logo_url'])
                            <div class="mb-3 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <img src="{{ $branding['logo_url'] }}" alt="Current logo" class="h-16 w-auto object-contain">
                                <span class="text-sm text-gray-500">Current logo</span>
                            </div>
                        @endif
                        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                        @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-6">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Invoice Maker Defaults</h3>
                <div class="mt-3 space-y-4">
                    <div>
                        <label for="default_template" class="mb-1 block text-sm font-medium text-gray-700">Default Template</label>
                        <select id="default_template" name="default_template" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                            @foreach($templates as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_template', $makerDefaults['default_template']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('default_template')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="terms_of_payment" class="mb-1 block text-sm font-medium text-gray-700">Terms of Payment</label>
                        <textarea id="terms_of_payment" name="terms_of_payment" rows="4"
                                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"
                                  placeholder="One bullet per line">{{ old('terms_of_payment', $makerDefaults['terms_of_payment']) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Each new line is shown as a bullet point on the invoice.</p>
                        @error('terms_of_payment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="pay_to" class="mb-1 block text-sm font-medium text-gray-700">Pay To</label>
                        <textarea id="pay_to" name="pay_to" rows="3"
                                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"
                                  placeholder="BCA&#10;5105251588&#10;CV ACTIVEWEAR GLOBAL MANDIRI">{{ old('pay_to', $makerDefaults['pay_to']) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">Line 1: bank name, line 2: account number, line 3: account holder name.</p>
                        @error('pay_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="signatory_name" class="mb-1 block text-sm font-medium text-gray-700">Signatory Name</label>
                        <input type="text" id="signatory_name" name="signatory_name"
                               value="{{ old('signatory_name', $makerDefaults['signatory_name']) }}"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                        @error('signatory_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="signature" class="mb-1 block text-sm font-medium text-gray-700">Signature Image</label>
                        @if($makerDefaults['signature_url'])
                            <div class="mb-3 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <img src="{{ $makerDefaults['signature_url'] }}" alt="Current signature" class="h-16 w-auto object-contain">
                                <span class="text-sm text-gray-500">Current signature</span>
                            </div>
                        @endif
                        <input type="file" id="signature" name="signature" accept="image/png,image/jpeg,image/webp"
                               class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                        @error('signature')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('system-settings.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Back</a>
                <button type="submit" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
