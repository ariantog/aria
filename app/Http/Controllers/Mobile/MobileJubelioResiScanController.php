<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Jubelio;
use App\Services\Jubelio\JubelioResiScanService;
use App\Services\UserPreferenceService;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class MobileJubelioResiScanController extends Controller
{
    public function options(Request $request, JubelioResiScanService $resiScan): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $user = $request->user();
        $preferences = app(UserPreferenceService::class);

        return response()->json([
            'marketplaces' => $resiScan->marketplaceOptions(),
            'warehouses' => $resiScan->warehouseOptions($user),
            'defaults' => [
                'warehouse_id' => (int) ($preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_WAREHOUSE_SLUG) ?? 0) ?: null,
                'marketplace' => $preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_MARKETPLACE_SLUG),
            ],
        ]);
    }

    public function updateDefaults(Request $request, UserPreferenceService $preferences): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $marketplaceKeys = array_keys(config('jubelio_resi_scan.marketplaces', []));

        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'marketplace' => ['nullable', 'string', Rule::in($marketplaceKeys)],
        ]);

        $user = $request->user();

        if (array_key_exists('warehouse_id', $validated)) {
            $preferences->set($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_WAREHOUSE_SLUG, $validated['warehouse_id']);
        }

        if (array_key_exists('marketplace', $validated)) {
            $preferences->set($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_MARKETPLACE_SLUG, $validated['marketplace']);
        }

        return response()->json([
            'defaults' => [
                'warehouse_id' => (int) ($preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_WAREHOUSE_SLUG) ?? 0) ?: null,
                'marketplace' => $preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_MARKETPLACE_SLUG),
            ],
            'message' => 'Defaults saved.',
        ]);
    }

    public function store(Request $request, JubelioResiScanService $resiScan, UserPreferenceService $preferences): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $marketplaceKeys = array_keys(config('jubelio_resi_scan.marketplaces', []));

        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'marketplace' => ['required', 'string', Rule::in($marketplaceKeys)],
            'barcodes' => ['required', 'array', 'min:1', 'max:200'],
            'barcodes.*' => ['required', 'string', 'max:255'],
            'remember_defaults' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        try {
            $sync = $resiScan->resolveSync($user, (int) $validated['warehouse_id'], $validated['marketplace']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $result = $resiScan->queueInvoices(
            $user,
            $sync,
            $validated['marketplace'],
            $validated['barcodes'],
        );

        if (! empty($validated['remember_defaults'])) {
            $preferences->set($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_WAREHOUSE_SLUG, (int) $validated['warehouse_id']);
            $preferences->set($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_MARKETPLACE_SLUG, $validated['marketplace']);
        }

        $createdCount = count($result['created']);
        $skippedCount = count($result['skipped']);

        return response()->json([
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'message' => $createdCount > 0
                ? "{$createdCount} resi diantri.".($skippedCount > 0 ? " {$skippedCount} dilewati." : '')
                : 'Tidak ada resi baru yang diantri.',
        ], $createdCount > 0 ? 201 : 422);
    }
}
