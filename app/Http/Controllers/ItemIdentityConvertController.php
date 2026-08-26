<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemIdentityConversionResult;
use App\Services\Items\LegacyItemConverterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ItemIdentityConvertController extends Controller
{
    public function __construct(
        protected LegacyItemConverterService $converterService,
    ) {}

    public function store(Item $item): RedirectResponse
    {
        $this->authorizeConverter();

        try {
            $result = $this->converterService->convertSingleFromDetail($item, auth()->user());
        } catch (\Throwable $e) {
            return redirect($item->showUrl())->with('error', $e->getMessage());
        }

        if ($result->status === ItemIdentityConversionResult::STATUS_SUCCESS) {
            $item->refresh();

            return redirect($item->showUrl())->with(
                'success',
                "Converted to canonical SKU {$item->code}. "
                .($item->legacy_code ? "Legacy SKU {$item->legacy_code} preserved for Jubelio." : ''),
            );
        }

        $detail = trim((string) ($result->detail ?? ''));
        $failure = trim((string) ($result->failure_code ?? 'CONVERSION_FAILED'));
        $message = $detail !== '' ? "{$failure}: {$detail}" : $failure;

        return redirect($item->showUrl())->with('error', $message);
    }

    protected function authorizeConverter(): void
    {
        $user = auth()->user();

        if ($user?->is_superadmin) {
            return;
        }

        Gate::authorize(Item::getPermissions()['convert-legacy']);
    }
}
