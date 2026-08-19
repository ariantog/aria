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

        $request->validate([
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if (! $request->file('logo')) {
            return redirect()->route('invoice-settings.edit')->with('success', 'No changes made.');
        }

        $brandingService->update($request->file('logo'));

        return redirect()->route('invoice-settings.edit')->with('success', 'Invoice logo saved.');
    }
}
