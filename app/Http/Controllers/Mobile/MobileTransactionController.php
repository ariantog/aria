<?php

namespace App\Http\Controllers\Mobile;

use App\Actions\Transactions\CreateCashTransaction;
use App\Actions\Transactions\CreateItemTransaction;
use App\Actions\Transactions\CreateTransferTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCashTransactionRequest;
use App\Http\Requests\StoreItemTransactionRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Transaction;
use App\Services\BookClosingService;
use App\Services\TransactionInvoiceService;
use App\Services\UserPreferenceService;
use App\Support\Mobile\MobilePosSupport;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MobileTransactionController extends Controller
{
    public function storeItem(
        StoreItemTransactionRequest $request,
        CreateItemTransaction $action,
        BookClosingService $bookClosingService,
        UserPreferenceService $preferences,
        TransactionInvoiceService $invoiceService,
    ): JsonResponse {
        $type = (string) $request->input('type');
        Transaction::authorizeTypeAccess($type);
        Gate::authorize(Transaction::getPermissions()['create']);

        $bookClosingService->validateDate($request->validated('date'));
        if ($request->wantsCashIn()) {
            Gate::authorize(Transaction::getPermissions()['type-cash-in']);
            $bookClosingService->validateDate(
                $request->validated('cash_in_date') ?: now()->toDateString(),
                'cash_in_date'
            );
        }

        $transaction = $action->execute($request);
        $user = $request->user();

        if ($type === 'sell' && $request->boolean('remember_defaults')) {
            $senderId = (int) $request->validated('sender_id');
            $receiverId = (int) $request->validated('receiver_id');
            $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_SENDER_ID_SLUG, $senderId);
            $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_RECEIVER_ID_SLUG, $receiverId);
            $waPhone = $request->input('whatsapp_phone');
            if (is_string($waPhone) && preg_match('/^[0-9]{8,15}$/', $waPhone)) {
                $preferences->set($user, UserPreferenceRegistry::MOBILE_POS_WHATSAPP_PHONE_SLUG, $waPhone);
            }
        }

        $payload = [
            'id' => $transaction->id,
            'message' => 'Transaction created.',
        ];

        if ($type === 'sell' && $request->boolean('pos')) {
            $wa = $request->input('whatsapp_phone');
            if (! is_string($wa) || $wa === '') {
                $stored = $preferences->get($user, UserPreferenceRegistry::MOBILE_POS_WHATSAPP_PHONE_SLUG);
                $wa = is_string($stored) ? $stored : null;
            }
            $payload['pos'] = MobilePosSupport::posExtras($transaction->fresh(['details.item', 'sender', 'receiver']), $wa, $invoiceService);
        }

        return response()->json($payload, 201);
    }

    public function storeCashIn(
        StoreCashTransactionRequest $request,
        CreateCashTransaction $action,
        BookClosingService $bookClosingService,
    ): JsonResponse {
        Gate::authorize(Transaction::getPermissions()['type-cash-in']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: true);

        return response()->json([
            'ids' => $ids,
            'message' => 'Cash in created.',
        ], 201);
    }

    public function storeCashOut(
        StoreCashTransactionRequest $request,
        CreateCashTransaction $action,
        BookClosingService $bookClosingService,
    ): JsonResponse {
        Gate::authorize(Transaction::getPermissions()['type-cash-out']);
        $bookClosingService->validateDate($request->validated('date'));
        $ids = $action->execute($request, isCashIn: false);

        return response()->json([
            'ids' => $ids,
            'message' => 'Cash out created.',
        ], 201);
    }

    public function storeTransfer(
        StoreTransferRequest $request,
        CreateTransferTransaction $action,
        BookClosingService $bookClosingService,
    ): JsonResponse {
        Gate::authorize(Transaction::getPermissions()['type-transfer']);
        $bookClosingService->validateDate($request->validated('date'));
        $transaction = $action->execute($request);

        return response()->json([
            'id' => $transaction->id,
            'message' => 'Transfer created.',
        ], 201);
    }
}
