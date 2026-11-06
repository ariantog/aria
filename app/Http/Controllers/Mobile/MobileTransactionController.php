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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MobileTransactionController extends Controller
{
    public function storeItem(
        StoreItemTransactionRequest $request,
        CreateItemTransaction $action,
        BookClosingService $bookClosingService,
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

        return response()->json([
            'id' => $transaction->id,
            'message' => 'Transaction created.',
        ], 201);
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
