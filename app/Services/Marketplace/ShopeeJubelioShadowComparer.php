<?php

namespace App\Services\Marketplace;

use App\Models\Jubelioorder;
use App\Models\MarketplaceOrderShadow;
use App\Services\Jubelio\JubelioOrderPayloadService;

class ShopeeJubelioShadowComparer
{
    public function __construct(
        private JubelioOrderPayloadService $payloadService,
        private JubelioOrderLineSummary $jubelioLineSummary,
        private ShopeeJubelioShadowLineCompare $lineCompare,
    ) {}

    /**
     * @param  array{lines: list<array{sku: string, qty: float, amount: float}>, line_count: int, total_amount: float|null}|null  $shopeeLineSummary
     * @return array{
     *     compare_status: string,
     *     jubelio_invoice: ?string,
     *     jubelio_order_status: ?string,
     *     jubelio_order_id: ?int,
     *     compare_note: ?string,
     *     jubelio_line_summary: ?array,
     *     line_compare_status: string,
     *     line_compare_note: ?string,
     * }
     */
    public function compare(string $orderSn, ?string $shopeeStatus, ?array $shopeeLineSummary = null): array
    {
        $prefix = strtoupper((string) config('jubelio_resi_scan.marketplaces.shopee.invoice_prefix', 'SP'));
        $baseInvoice = $prefix.'-'.$orderSn;

        $jubelio = Jubelioorder::query()
            ->where('type', 'SELL')
            ->where(function ($query) use ($baseInvoice, $orderSn, $prefix) {
                $query->where('invoice', $baseInvoice)
                    ->orWhere('invoice', 'like', $prefix.'-'.$orderSn.'-%');
            })
            ->orderByDesc('id')
            ->first();

        $lineCompareStatus = MarketplaceOrderShadow::LINE_COMPARE_SKIPPED;
        $jubelioLineSummary = null;
        $lineCompareNote = null;

        if ($jubelio === null) {
            return [
                'compare_status' => MarketplaceOrderShadow::COMPARE_JUBELIO_MISSING,
                'jubelio_invoice' => null,
                'jubelio_order_status' => null,
                'jubelio_order_id' => null,
                'compare_note' => 'Belum ada baris jubelioorders untuk invoice '.$baseInvoice,
                'jubelio_line_summary' => null,
                'line_compare_status' => $lineCompareStatus,
                'line_compare_note' => null,
            ];
        }

        $statusMismatch = false;
        $compareNote = null;

        $jubStatus = strtoupper(trim((string) $jubelio->order_status));
        $shopStatus = strtoupper(trim((string) ($shopeeStatus ?? '')));

        if ($shopStatus !== '' && $jubStatus !== '' && $shopStatus !== $jubStatus) {
            $statusMismatch = true;
            $compareNote = 'Status Shopee '.$shopStatus.' vs Jubelio '.$jubStatus;
        }

        if ($shopeeLineSummary !== null && $shopeeLineSummary['line_count'] > 0) {
            $payload = $this->payloadService->fetch($jubelio);
            $jubelioLineSummary = $this->jubelioLineSummary->fromPayload($payload);
            $lineResult = $this->lineCompare->compare($shopeeLineSummary, $jubelioLineSummary);
            $lineCompareStatus = $lineResult['line_compare_status'];
            $lineCompareNote = $lineResult['line_compare_note'];

            if ($lineCompareStatus === MarketplaceOrderShadow::LINE_COMPARE_MISMATCH) {
                $compareNote = $compareNote
                    ? $compareNote.'; '.$lineCompareNote
                    : $lineCompareNote;
            }
        }

        $lineMismatch = $lineCompareStatus === MarketplaceOrderShadow::LINE_COMPARE_MISMATCH;
        $overallMatch = ! $statusMismatch && ! $lineMismatch;

        return [
            'compare_status' => $overallMatch
                ? MarketplaceOrderShadow::COMPARE_MATCHED
                : MarketplaceOrderShadow::COMPARE_MISMATCH,
            'jubelio_invoice' => $jubelio->invoice,
            'jubelio_order_status' => $jubelio->order_status,
            'jubelio_order_id' => (int) $jubelio->id,
            'compare_note' => $compareNote,
            'jubelio_line_summary' => $jubelioLineSummary,
            'line_compare_status' => $lineCompareStatus,
            'line_compare_note' => $lineCompareNote,
        ];
    }
}