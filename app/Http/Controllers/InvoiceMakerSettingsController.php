<?php

namespace App\Http\Controllers;

use App\Models\StandaloneInvoice;
use App\Services\InvoiceMakerSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class InvoiceMakerSettingsController extends Controller
{
    public function index(InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        return view('invoice-maker.settings.index', [
            'presets' => $settingsService->presets(),
            'defaultPresetId' => \App\Models\Setting::getValue(InvoiceMakerSettingsService::SETTING_DEFAULT_PRESET_ID),
            'templates' => StandaloneInvoice::TEMPLATES,
        ]);
    }

    public function create(InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        return view('invoice-maker.settings.form', [
            'preset' => null,
            'templates' => StandaloneInvoice::TEMPLATES,
            'defaultPreset' => $settingsService->defaultPreset(),
        ]);
    }

    public function store(Request $request, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $validated = $this->validatedPreset($request);
        $settingsService->createPreset($validated, $request->file('signature'), $request->file('logo'));

        return redirect()
            ->route('invoice-maker.settings.index')
            ->with('success', 'Invoice preset created.');
    }

    public function edit(string $preset, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $found = $settingsService->findPreset($preset);
        abort_unless($found, 404);

        return view('invoice-maker.settings.form', [
            'preset' => $found,
            'templates' => StandaloneInvoice::TEMPLATES,
            'defaultPreset' => $settingsService->defaultPreset(),
        ]);
    }

    public function update(Request $request, string $preset, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $validated = $this->validatedPreset($request);
        $settingsService->updatePreset($preset, $validated, $request->file('signature'), $request->file('logo'));

        if ($request->boolean('is_default')) {
            $settingsService->setDefaultPreset($preset);
        }

        return redirect()
            ->route('invoice-maker.settings.index')
            ->with('success', 'Invoice preset updated.');
    }

    public function destroy(string $preset, InvoiceMakerSettingsService $settingsService)
    {
        Gate::authorize(StandaloneInvoice::getPermissions()['edit']);

        $settingsService->deletePreset($preset);

        return redirect()
            ->route('invoice-maker.settings.index')
            ->with('success', 'Invoice preset deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPreset(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'terms_of_payment' => ['nullable', 'string', 'max:5000'],
            'pay_to' => ['nullable', 'string', 'max:1000'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'template' => ['required', 'string', Rule::in(array_keys(StandaloneInvoice::TEMPLATES))],
            'signature' => ['nullable', 'image', 'max:2048'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}
