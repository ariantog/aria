<?php

namespace App\Services\Jubelio;

use App\Services\JubelioService;
use Illuminate\Support\Facades\Log;

class JubelioResiInvoiceResolver
{
    private ?string $lastLookupMessage = null;

    public function __construct(
        private JubelioService $jubelioService,
    ) {}

    public function lastLookupMessage(): ?string
    {
        return $this->lastLookupMessage;
    }

    /**
     * @return array{invoice: string, salesorder_id: string|int|null}|null
     */
    public function resolveInvoice(string $marketplaceKey, string $rawBarcode, ?int $jubelioStoreId = null): ?array
    {
        $this->lastLookupMessage = null;

        if (! $this->marketplaceResolvesViaTracking($marketplaceKey)) {
            return null;
        }

        if (! (bool) config('services.jubelio.active', false)) {
            Log::info('Resi tracking lookup skipped — Jubelio inactive.', ['marketplace' => $marketplaceKey]);

            return null;
        }

        $result = $this->jubelioService->resolveSalesOrderByTracking($rawBarcode, $jubelioStoreId);

        if (($result['status'] ?? '') !== 'found') {
            Log::info('Jubelio resi tracking lookup missed.', [
                'marketplace' => $marketplaceKey,
                'store_id' => $jubelioStoreId,
                'tracking' => $rawBarcode,
                'status' => $result['status'] ?? 'unknown',
            ]);

            $this->lastLookupMessage = (string) ($result['message'] ?? 'Resi tidak ditemukan di Jubelio.');

            return null;
        }

        return [
            'invoice' => $result['salesorder_no'],
            'salesorder_id' => $result['salesorder_id'],
        ];
    }

    public function marketplaceResolvesViaTracking(string $marketplaceKey): bool
    {
        $config = config('jubelio_resi_scan.marketplaces.'.$marketplaceKey, []);

        return (bool) ($config['resolve_invoice_from_tracking'] ?? false);
    }
}
