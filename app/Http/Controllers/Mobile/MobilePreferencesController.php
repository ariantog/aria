<?php

namespace App\Http\Controllers\Mobile;

use App\Enums\AddrbookType;
use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\UserPreferenceService;
use App\Support\Mobile\MobilePosSupport;
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

    public function updatePosDefaults(Request $request, UserPreferenceService $preferences): JsonResponse
    {
        $validated = $request->validate([
            'sender_id' => ['nullable', 'integer', 'min:1'],
            'receiver_id' => ['nullable', 'integer', 'min:1'],
            'whatsapp_phone' => ['nullable', 'string', 'regex:/^[0-9]{8,15}$/'],
        ]);

        $user = $request->user();
        $locationAccess = app(LocationAccessService::class);

        if (array_key_exists('sender_id', $validated)) {
            $id = $validated['sender_id'];
            if ($id === null) {
                $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG, null);
            } else {
                $contact = Addrbook::find((int) $id);
                if (! $contact || $contact->typeValue() !== AddrbookType::Warehouse->value) {
                    return response()->json(['message' => 'Warehouse (sender) is not valid.'], 422);
                }
                if (! $locationAccess->canAccessAddrbook($user, $contact)) {
                    return response()->json(['message' => 'Warehouse is not available for your location.'], 422);
                }
                $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG, (int) $id);
            }
        }

        if (array_key_exists('receiver_id', $validated)) {
            $id = $validated['receiver_id'];
            if ($id === null) {
                $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_RECEIVER_ID_SLUG, null);
            } else {
                $contact = Addrbook::find((int) $id);
                $type = $contact?->typeValue();
                if (! $contact || ! in_array($type, [AddrbookType::Customer->value, AddrbookType::Reseller->value], true)) {
                    return response()->json(['message' => 'Customer / reseller (receiver) is not valid.'], 422);
                }
                if (! $locationAccess->canAccessAddrbook($user, $contact)) {
                    return response()->json(['message' => 'Customer is not available for your location.'], 422);
                }
                $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_RECEIVER_ID_SLUG, (int) $id);
            }
        }

        if (array_key_exists('whatsapp_phone', $validated)) {
            $preferences->set(
                $user,
                UserPreferenceRegistry::MOBILE_POS_WHATSAPP_PHONE_SLUG,
                $validated['whatsapp_phone'],
            );
        }

        return response()->json([
            'pos' => self::posDefaultsPayload($user, $preferences),
            'message' => 'POS defaults saved.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function posDefaultsPayload(User $user, UserPreferenceService $preferences): array
    {
        $locationAccess = app(LocationAccessService::class);

        $senderId = (int) ($preferences->get($user, UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG) ?? 0) ?: null;
        $receiverId = (int) ($preferences->get($user, UserPreferenceRegistry::MOBILE_POS_RECEIVER_ID_SLUG) ?? 0) ?: null;

        if (! $senderId || ! $receiverId) {
            $sellPrefill = $preferences->transactionPrefill($user, 'sell');
            if (is_array($sellPrefill)) {
                $senderId ??= isset($sellPrefill['sender_id']) ? (int) $sellPrefill['sender_id'] : null;
                $receiverId ??= isset($sellPrefill['receiver_id']) ? (int) $sellPrefill['receiver_id'] : null;
            }
        }

        $sender = null;
        $receiver = null;
        if ($senderId) {
            $contact = Addrbook::find($senderId);
            if ($contact && $locationAccess->canAccessAddrbook($user, $contact)) {
                $sender = MobilePosSupport::addrbookSummary($contact);
            }
        }
        if ($receiverId) {
            $contact = Addrbook::find($receiverId);
            if ($contact && $locationAccess->canAccessAddrbook($user, $contact)) {
                $receiver = MobilePosSupport::addrbookSummary($contact);
            }
        }

        $wa = $preferences->get($user, UserPreferenceRegistry::MOBILE_POS_WHATSAPP_PHONE_SLUG);
        $whatsappPhone = is_string($wa) && $wa !== '' ? $wa : null;

        return [
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'sender' => $sender,
            'receiver' => $receiver,
            'whatsapp_phone' => $whatsappPhone,
        ];
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
            'pos' => self::posDefaultsPayload($user, $preferences),
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
