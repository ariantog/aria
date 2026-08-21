@extends('layouts.app')

@section('title', $preset ? 'Edit Preset' : 'New Preset')

@section('content')
@php
$isEdit = (bool) $preset;
$breadcrumbs = [
    ['title' => 'Invoice Maker', 'href' => route('invoice-maker.index')],
    ['title' => 'Settings', 'href' => route('invoice-maker.settings.index')],
    ['title' => $isEdit ? 'Edit' : 'New', 'href' => $isEdit ? route('invoice-maker.settings.edit', $preset['id']) : route('invoice-maker.settings.create')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $isEdit ? 'Edit Preset' : 'New Preset' }}</h2>
        <p class="mt-0.5 text-sm text-gray-500">Presets appear in the invoice form dropdown for logo, terms, pay-to, signatory, signature, and template.</p>
    </div>

    <div class="max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <form method="POST"
              action="{{ $isEdit ? route('invoice-maker.settings.update', $preset['id']) : route('invoice-maker.settings.store') }}"
              enctype="multipart/form-data"
              class="space-y-4 p-6">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Preset Name</label>
                <input type="text" id="name" name="name" required
                       value="{{ old('name', $preset['name'] ?? '') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="logo" class="mb-1 block text-sm font-medium text-gray-700">Invoice Logo</label>
                @if($isEdit && ($preset['logo_url'] ?? null))
                    <div class="mb-3 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <img src="{{ $preset['logo_url'] }}" alt="Current logo" class="h-16 w-auto object-contain">
                        <span class="text-sm text-gray-500">Current logo</span>
                    </div>
                @endif
                <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                <p class="mt-1 text-xs text-gray-500">PNG, JPG, or WebP. Max 2 MB. Shown on the invoice PDF for this preset.</p>
                @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="template" class="mb-1 block text-sm font-medium text-gray-700">Template</label>
                <select id="template" name="template" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                    @foreach($templates as $value => $label)
                        <option value="{{ $value }}" @selected(old('template', $preset['template'] ?? $defaultPreset['template']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('template')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="terms_of_payment" class="mb-1 block text-sm font-medium text-gray-700">Terms of Payment</label>
                <textarea id="terms_of_payment" name="terms_of_payment" rows="4"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"
                          placeholder="One bullet per line">{{ old('terms_of_payment', $preset['terms_of_payment'] ?? '') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Each new line is shown as a bullet point on the invoice.</p>
                @error('terms_of_payment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="pay_to" class="mb-1 block text-sm font-medium text-gray-700">Pay To</label>
                <textarea id="pay_to" name="pay_to" rows="3"
                          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500"
                          placeholder="BCA&#10;5105251588&#10;CV ACTIVEWEAR GLOBAL MANDIRI">{{ old('pay_to', $preset['pay_to'] ?? '') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">Line 1: bank, line 2: account number, line 3: account holder name.</p>
                @error('pay_to')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="signatory_name" class="mb-1 block text-sm font-medium text-gray-700">Signatory Name</label>
                <input type="text" id="signatory_name" name="signatory_name"
                       value="{{ old('signatory_name', $preset['signatory_name'] ?? '') }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500">
                @error('signatory_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="signature" class="mb-1 block text-sm font-medium text-gray-700">Signature Image</label>
                @if($isEdit && ($preset['signature_url'] ?? null))
                    <div class="mb-3 flex items-center gap-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <img src="{{ $preset['signature_url'] }}" alt="Current signature" class="h-16 w-auto object-contain">
                        <span class="text-sm text-gray-500">Current signature</span>
                    </div>
                @endif
                <input type="file" id="signature" name="signature" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100">
                @error('signature')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @if($isEdit)
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $defaultPreset['id'] === $preset['id'])) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                Set as default preset for new invoices
            </label>
            @endif

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('invoice-maker.settings.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" data-testid="save-preset-button" class="rounded-lg bg-blue-700 px-6 py-2 text-sm font-medium text-white hover:bg-blue-800">Save Preset</button>
            </div>
        </form>

        @if($isEdit)
        <form method="POST" action="{{ route('invoice-maker.settings.destroy', $preset['id']) }}" onsubmit="return confirm('Delete this preset?')" class="border-t border-gray-100 px-6 py-4">
            @csrf
            @method('DELETE')
            <button type="submit" data-testid="delete-preset-button" class="text-sm font-medium text-red-600 hover:text-red-700">Delete preset</button>
        </form>
        @endif
    </div>
</div>
@endsection
