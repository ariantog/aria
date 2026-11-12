<?php

namespace App\Services\Jubelio;

use App\Services\JubelioService;
use Illuminate\Support\Facades\Log;

class JubelioResiInvoiceResolver
{
    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    /**
     * @return array{invoice: string, salesorder_id: string|int|null}|null
     */
    public function resolveInvoice(string $marketplaceKey, string $rawBarcode, ?int $jubelioStoreId = null): ?array
    {
        if (! $this->marketplaceResolvesViaTracking($marketplaceKey)) {
            return null;
        }

        if (! (bool) config('services.jubelio.active', false)) {
            Log::info('Resi tracking lookup skipped — Jubelio inactive.', ['marketplace' => $marketplaceKey]);

            return null;
        }

        $days = (int) config('jubelio_resi_scan.tracking_lookup_days', 14);
        $maxPages = (int) config('jubelio_resi_scan.tracking_lookup_max_pages', 5);

        $match = $this->jubelioService->findSalesOrderByTrackingNo(
            $rawBarcode,
            $jubelioStoreId,
            $days,
            $maxPages,
        );

        if ($match === null) {
            return null;
        }

        return [
            'invoice' => $match['salesorder_no'],
            'salesorder_id' => $match['salesorder_id'],
        ];
    }

    public function marketplaceResolvesViaTracking(string $marketplaceKey): bool
    {
        $config = config('jubelio_resi_scan.marketplaces.'.$marketplaceKey, []);

        return (bool) ($config['resolve_invoice_from_tracking'] ?? false);
    }
}
