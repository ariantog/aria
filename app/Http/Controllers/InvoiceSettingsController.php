<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\StandaloneInvoice;
use App\Services\InvoiceBrandingService;
use App\Services\InvoiceMakerSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceSettingsController extends Controller
{
    public function edit(InvoiceBrandingService $brandingService, InvoiceMakerSettingsService $makerSettings)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        return view('system-settings.invoice', [
            'branding' => $brandingService->branding(),
            'makerDefaults' => $makerSettings->defaults(),
            'templates' => StandaloneInvoice::TEMPLATES,
        ]);
    }

    public function update(Request $request, InvoiceBrandingService $brandingService, InvoiceMakerSettingsService $makerSettings)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'terms_of_payment' => ['nullable', 'string', 'max:5000'],
            'pay_to' => ['nullable', 'string', 'max:1000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'default_template' => ['nullable', 'string', Rule::in(array_keys(StandaloneInvoice::TEMPLATES))],
        ]);

        $makerSettings->updateDefaults([
            'terms_of_payment' => $validated['terms_of_payment'] ?? null,
            'pay_to' => $validated['pay_to'] ?? null,
            'signatory_name' => $validated['signatory_name'] ?? null,
            'default_template' => $validated['default_template'] ?? null,
        ], $request->file('signature'));

        if ($request->file('logo')) {
            $brandingService->update($request->file('logo'));
        }

        return redirect()->route('invoice-settings.edit')->with('success', 'Invoice settings saved.');
    }
}
