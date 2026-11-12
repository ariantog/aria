<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\UserPreferenceService;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobilePreferencesController extends Controller
{
    public function updateAppearance(Request $request, UserPreferenceService $preferences): JsonResponse
    {
        $validated = $request->validate([
            'appearance' => ['required', 'string', Rule::in(UserPreferenceRegistry::appearanceOptions())],
        ]);

        $preferences->setAppearance($request->user(), $validated['appearance']);

        return response()->json([
            'appearance' => $preferences->appearanceFor($request->user()),
            'message' => 'Appearance saved.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function preferencePayload(User $user, UserPreferenceService $preferences): array
    {
        $locationAccess = app(LocationAccessService::class);

        $form = $preferences->transactionDefaultsFormData($user);

        $transactionParties = [];
        /** @var array<string, mixed> $values */
        $values = $form['values'];
        foreach ($values as $field => $id) {
            $contact = $form['contacts'][$field] ?? null;
            $transactionParties[$field] = $contact instanceof Addrbook
                ? self::addrbookSummary($contact)
                : null;
        }

        $prefillTypes = ['buy', 'sell', 'move', 'return', 'return-supplier'];
        $prefill = [];
        foreach ($prefillTypes as $type) {
            $p = $preferences->transactionPrefill($user, $type);
            if ($p !== null) {
                $prefill[$type] = self::normalizePrefill($p);
            }
        }

        $transferIds = $preferences->defaultTransferAccounts($user);
        $transfer = $transferIds;
        foreach (['sender_id' => 'sender', 'receiver_id' => 'receiver'] as $idKey => $partyKey) {
            $id = $transferIds[$idKey] ?? null;
            if (! $id) {
                $transfer[$partyKey] = null;

                continue;
            }
            $contact = Addrbook::find((int) $id);
            $transfer[$partyKey] = ($contact && $locationAccess->canAccessAddrbook($user, $contact))
                ? self::addrbookSummary($contact)
                : null;
        }

        return [
            'appearance' => $preferences->appearanceFor($user),
            'transaction_parties' => $transactionParties,
            'cash_in_bank' => $preferences->defaultCashAccount($user, true),
            'cash_out_bank' => $preferences->defaultCashAccount($user, false),
            'transfer' => $transfer,
            'prefill' => $prefill,
            'resi_scan' => [
                'warehouse_id' => (int) ($preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_WAREHOUSE_SLUG) ?? 0) ?: null,
                'marketplace' => $preferences->get($user, UserPreferenceRegistry::MOBILE_RESI_SCAN_MARKETPLACE_SLUG),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $prefill
     * @return array<string, mixed>
     */
    private static function normalizePrefill(array $prefill): array
    {
        $out = [];
        foreach (['sender_id', 'receiver_id'] as $key) {
            if (! array_key_exists($key, $prefill)) {
                continue;
            }
            $raw = $prefill[$key];
            if ($raw === null || $raw === '') {
                $out[$key] = null;

                continue;
            }
            $out[$key] = (int) $raw;
        }
        foreach (['sender', 'receiver'] as $partyKey) {
            if (! array_key_exists($partyKey, $prefill)) {
                continue;
            }
            $party = $prefill[$partyKey];
            $out[$partyKey] = is_array($party) ? $party : null;
        }

        return $out;
    }

    /**
     * @return array{id: int, name: string, ppn: bool, ppn_included: bool}
     */
    private static function addrbookSummary(Addrbook $addrbook): array
    {
        return [
            'id' => $addrbook->id,
            'name' => $addrbook->name,
            'ppn' => (bool) $addrbook->ppn,
            'ppn_included' => $addrbook->resolvedPpnIncluded(),
        ];
    }
}
