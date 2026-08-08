<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\InvoiceBrandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InvoiceSettingsController extends Controller
{
    public function edit(InvoiceBrandingService $brandingService)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        return view('system-settings.invoice', [
            'branding' => $brandingService->branding(),
        ]);
    }

    public function update(Request $request, InvoiceBrandingService $brandingService)
    {
        Gate::authorize(Setting::getPermissions()['edit']);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:40'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $brandingService->update($validated, $request->file('logo'));

        return redirect()->route('invoice-settings.edit')->with('success', 'Invoice settings saved.');
    }
}
