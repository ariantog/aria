<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\LocationAccessService;
use App\Services\TransactionInvoiceService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MobileTransactionReceiptController extends Controller
{
    public function __construct(
        protected LocationAccessService $locationAccess,
    ) {}

    public function show(Transaction $transaction, TransactionInvoiceService $invoiceService): BinaryFileResponse
    {
        Gate::authorize(Transaction::getPermissions()['show']);
        abort_unless(
            $this->locationAccess->canAccessTransaction(request()->user(), $transaction),
            403
        );

        $invoiceService->createReceiptPdf($transaction, regenerate: false);
        $fileName = $invoiceService->receiptFileName($transaction);
        $filePath = $invoiceService->invoiceDiskPath($fileName);

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }
}
