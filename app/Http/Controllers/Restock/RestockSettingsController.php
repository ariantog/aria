<?php

namespace App\Http\Controllers\Restock;

use App\Enums\AddrbookType;
use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\RestockSheet;
use App\Services\Restock\RestockSettingsService;
use App\Services\Restock\RestockSheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class RestockSettingsController extends Controller
{
    public function __construct(
        protected RestockSettingsService $settingsService,
        protected RestockSheetService $sheetService,
    ) {}

    public function edit(): View
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        return view('restock.settings', [
            'settings' => $this->settingsService->formData(),
            'typeTags' => $this->sheetService->typeTags(),
            'canEdit' => request()->user()?->can(RestockSheet::getPermissions()['edit']) ?? false,
            'supplierLookupUrl' => route('restock.settings.lookup', ['type' => 'supplier']),
            'receiverLookupUrl' => route('restock.settings.lookup', ['type' => 'warehouse']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize(RestockSheet::getPermissions()['edit']);

        $validated = $request->validate([
            'default_supplier_id' => ['required', 'integer', 'exists:customers,id'],
            'default_receiver_id' => ['required', 'integer', 'exists:customers,id'],
            'default_warehouse_ids' => ['nullable', 'array'],
            'default_warehouse_ids.*' => ['integer', 'exists:customers,id'],
        ]);

        try {
            $this->settingsService->update($validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('restock.settings.edit')
            ->with('success', 'Restock settings saved.');
    }

    public function lookup(Request $request, string $type)
    {
        Gate::authorize(RestockSheet::getPermissions()['view']);

        abort_unless(in_array($type, ['supplier', 'warehouse'], true), 404);

        $addrbookType = $type === 'supplier'
            ? AddrbookType::Supplier->value
            : AddrbookType::Warehouse->value;

        $query = Addrbook::query()->where('type', $addrbookType);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(20)->get(['id', 'name'])
        );
    }
}
