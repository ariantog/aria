<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\Marketplace\ShopeeJubelioQueuePrecreator;
use App\Services\Marketplace\ShopeeSyncInvoicePrefix;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JubelioResiScanService
{
    public function __construct(
        private JubelioSellInvoiceGuard $sellInvoiceGuard,
        private LocationAccessService $locationAccess,
        private JubelioResiInvoiceResolver $invoiceResolver,
        private ShopeeSyncInvoicePrefix $shopeeInvoicePrefix,
    ) {}

    /**
     * @return array<string, array{label: string, invoice_prefix: string, store_name_patterns: list<string>}>
     */
    public function marketplacesConfig(): array
    {
        /** @var array<string, array{label: string, invoice_prefix: string, store_name_patterns: list<string>}> $marketplaces */
        $marketplaces = config('jubelio_resi_scan.marketplaces', []);

        return $marketplaces;
    }

    /**
     * @return list<array{key: string, label: string, invoice_prefix: string}>
     */
    public function marketplaceOptions(): array
    {
        $out = [];
        foreach ($this->marketplacesConfig() as $key => $row) {
            $out[] = [
                'key' => $key,
                'label' => $row['label'],
                'invoice_prefix' => $row['invoice_prefix'],
            ];
        }

        return $out;
    }

    public function formatInvoice(string $marketplaceKey, string $rawBarcode, ?int $jubelioStoreId = null): string
    {
        $config = $this->marketplacesConfig()[$marketplaceKey] ?? null;
        if ($config === null) {
            throw new InvalidArgumentException('Marketplace tidak dikenal.');
        }

        $raw = trim($rawBarcode);
        if ($raw === '') {
            throw new InvalidArgumentException('Nomor resi kosong.');
        }

        $prefix = strtoupper((string) $config['invoice_prefix']);
        $upper = strtoupper($raw);

        if (str_starts_with($upper, $prefix.'-')) {
            $invoice = $prefix.'-'.substr($raw, strlen($prefix) + 1);
        } elseif (preg_match('/^[A-Z]{2}-/i', $raw) === 1) {
            $invoice = $raw;
        } else {
            $invoice = $prefix.'-'.$raw;
        }

        return $this->appendJubelioStoreIdIfConfigured($marketplaceKey, $invoice, $jubelioStoreId);
    }

    public function appendJubelioStoreIdIfConfigured(string $marketplaceKey, string $invoice, ?int $jubelioStoreId): string
    {
        $config = $this->marketplacesConfig()[$marketplaceKey] ?? [];
        if (! (bool) ($config['append_jubelio_store_id'] ?? false)) {
            return $invoice;
        }

        if ($jubelioStoreId === null || ! Jubeliosync::isMappedStoreId($jubelioStoreId)) {
            return $invoice;
        }

        $suffix = '-'.$jubelioStoreId;
        if (str_ends_with($invoice, $suffix)) {
            return $invoice;
        }

        return $invoice.$suffix;
    }

    /**
     * @return Collection<int, Jubeliosync>
     */
    public function syncRowsForUser(User $user): Collection
    {
        $query = Jubeliosync::query()->with(['warehouse:id,name']);

        if (! $this->locationAccess->hasUnrestrictedLocationAccess($user)) {
            $warehouseIds = Addrbook::query()
                ->where('type', Addrbook::TYPE_WAREHOUSE)
                ->visibleAtLocation((int) $user->location_id)
                ->pluck('id');

            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query->orderBy('warehouse_id')->orderBy('jubelio_store_name')->get();
    }

    /**
     * @return list<array{
     *   id: int,
     *   name: string,
     *   marketplaces: list<array{key: string, label: string, sync_id: int, jubelio_store_name: string}>
     * }>
     */
    public function warehouseOptions(User $user): array
    {
        $byWarehouse = [];

        foreach ($this->syncRowsForUser($user) as $sync) {
            $warehouseId = (int) $sync->warehouse_id;
            if ($warehouseId <= 0) {
                continue;
            }

            $warehouse = $sync->warehouse;
            if ($warehouse instanceof Addrbook && ! $this->locationAccess->canAccessAddrbook($user, $warehouse)) {
                continue;
            }

            $marketplaceKey = $this->resolveMarketplaceKeyForStore((string) $sync->jubelio_store_name);
            if ($marketplaceKey === null) {
                continue;
            }

            $config = $this->marketplacesConfig()[$marketplaceKey];

            if (! isset($byWarehouse[$warehouseId])) {
                $byWarehouse[$warehouseId] = [
                    'id' => $warehouseId,
                    'name' => $warehouse instanceof Addrbook ? $warehouse->name : 'Gudang #'.$warehouseId,
                    'marketplaces' => [],
                ];
            }

            $byWarehouse[$warehouseId]['marketplaces'][] = [
                'key' => $marketplaceKey,
                'label' => $config['label'],
                'sync_id' => (int) $sync->id,
                'jubelio_store_name' => $sync->jubelio_store_name,
            ];
        }

        foreach ($byWarehouse as &$row) {
            usort($row['marketplaces'], fn (array $a, array $b) => strcmp($a['label'], $b['label']));
        }
        unset($row);

        return array_values($byWarehouse);
    }

    public function resolveSync(User $user, int $warehouseId, string $marketplaceKey): Jubeliosync
    {
        if (! isset($this->marketplacesConfig()[$marketplaceKey])) {
            throw new InvalidArgumentException('Marketplace tidak dikenal.');
        }

        $warehouse = Addrbook::query()->find($warehouseId);
        if (! $warehouse || (int) $warehouse->type !== Addrbook::TYPE_WAREHOUSE) {
            throw new InvalidArgumentException('Gudang tidak valid.');
        }

        if (! $this->locationAccess->canAccessAddrbook($user, $warehouse)) {
            throw new InvalidArgumentException('Anda tidak memiliki akses ke gudang ini.');
        }

        $matches = $this->syncRowsForUser($user)
            ->filter(fn (Jubeliosync $sync) => (int) $sync->warehouse_id === $warehouseId)
            ->filter(fn (Jubeliosync $sync) => $this->resolveMarketplaceKeyForStore((string) $sync->jubelio_store_name) === $marketplaceKey)
            ->values();

        if ($matches->isEmpty()) {
            throw new InvalidArgumentException('Tidak ada mapping Jubelio untuk gudang dan marketplace ini.');
        }

        if ($matches->count() > 1) {
            throw new InvalidArgumentException('Mapping Jubelio ganda untuk kombinasi ini — hubungi admin.');
        }

        return $matches->first();
    }

    /**
     * @param  list<string>  $rawBarcodes
     * @return array{
     *   created: list<array{id: int, invoice: string}>,
     *   updated: list<array{id: int, invoice: string}>,
     *   skipped: list<array{raw: string, invoice: string|null, reason: string}>
     * }
     */
    public function queueInvoices(User $user, Jubeliosync $sync, string $marketplaceKey, array $rawBarcodes): array
    {
        $created = [];
        $updated = [];
        $skipped = [];
        $seenInvoices = [];

        foreach ($rawBarcodes as $raw) {
            $rawString = is_string($raw) ? trim($raw) : '';
            if ($rawString === '') {
                continue;
            }

            try {
                $resolved = $this->resolveInvoiceForBarcode(
                    $marketplaceKey,
                    $rawString,
                    (int) $sync->jubelio_store_id,
                );
                $invoice = $resolved['invoice'];
            } catch (InvalidArgumentException $e) {
                $skipped[] = ['raw' => $rawString, 'invoice' => null, 'reason' => $e->getMessage()];

                continue;
            }

            if (isset($seenInvoices[$invoice])) {
                $skipped[] = ['raw' => $rawString, 'invoice' => $invoice, 'reason' => 'Duplikat dalam pengiriman ini.'];

                continue;
            }
            $seenInvoices[$invoice] = true;

            if ($this->sellInvoiceGuard->sellInvoiceInTransactions($invoice)) {
                $skipped[] = ['raw' => $rawString, 'invoice' => $invoice, 'reason' => 'Invoice sudah diposting ke transaksi.'];

                continue;
            }

            $existing = $this->findQueueRowForResiScan($invoice, $resolved['jubelio_order_id'] ?? null);

            if ($existing !== null) {
                $this->touchExistingFromScan(
                    $existing,
                    $user,
                    $sync,
                    $resolved['jubelio_order_id'] ?? null,
                );
                $updated[] = ['id' => (int) $existing->id, 'invoice' => $existing->invoice];

                continue;
            }

            if ($this->marketplaceInsertsQueueRowOnScan($marketplaceKey)) {
                $jubelioOrderId = $this->placeholderJubelioOrderId($invoice, $resolved['jubelio_order_id'] ?? null);

                $order = Jubelioorder::create([
                    'jubelio_order_id' => $jubelioOrderId,
                    'source' => (int) config('jubelio_resi_scan.source', Jubelioorder::SOURCE_SCAN_RESI),
                    'invoice' => $invoice,
                    'type' => 'SELL',
                    'order_status' => JubelioOrderReadyToShip::STATUS,
                    'run_count' => 0,
                    'warehouse_id' => (int) $sync->warehouse_id,
                    'jubelio_store_id' => (int) $sync->jubelio_store_id,
                    'jubelio_location_id' => (int) $sync->jubelio_location_id,
                    'user_id' => (int) $user->id,
                    'status' => 0,
                ]);

                $created[] = ['id' => (int) $order->id, 'invoice' => $invoice];

                continue;
            }

            $skipped[] = [
                'raw' => $rawString,
                'invoice' => $invoice,
                'reason' => 'Belum ada di antrean Jubelio — tunggu push marketplace atau webhook.',
            ];
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    public function marketplaceInsertsQueueRowOnScan(string $marketplaceKey): bool
    {
        $config = $this->marketplacesConfig()[$marketplaceKey] ?? [];

        return (bool) ($config['insert_queue_row_on_scan'] ?? false);
    }

    /**
     * Match an existing queue row by canonical invoice, Jubelio order id, or Shopee push placeholder.
     */
    private function findQueueRowForResiScan(string $invoice, string|int|null $jubelioOrderId): ?Jubelioorder
    {
        foreach ($this->invoiceMatchCandidates($invoice) as $candidate) {
            $byInvoice = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('invoice', $candidate)
                ->first();

            if ($byInvoice !== null) {
                return $byInvoice;
            }
        }

        $jubelioId = $jubelioOrderId !== null ? trim((string) $jubelioOrderId) : '';
        if ($jubelioId !== '') {
            $byJubelioId = Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('jubelio_order_id', $jubelioId)
                ->first();

            if ($byJubelioId !== null) {
                return $byJubelioId;
            }
        }

        $orderSn = $this->shopeeOrderSnFromPrefixedInvoice($invoice);
        if ($orderSn !== null) {
            $placeholderId = ShopeeJubelioQueuePrecreator::PLACEHOLDER_PREFIX.$orderSn;

            return Jubelioorder::query()
                ->where('type', 'SELL')
                ->where('jubelio_order_id', $placeholderId)
                ->first();
        }

        return null;
    }

    /**
     * Jubelio may return `TT-…-{store_id}` while an older queue row omitted the suffix (or the reverse).
     *
     * @return list<string>
     */
    private function invoiceMatchCandidates(string $invoice): array
    {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return [];
        }

        $out = [$invoice];

        if (preg_match('/^(TT|TP)-(.+)-(\d+)$/i', $invoice, $matches) === 1) {
            $withoutSuffix = strtoupper($matches[1]).'-'.$matches[2];
            if (! in_array($withoutSuffix, $out, true)) {
                $out[] = $withoutSuffix;
            }
        }

        return $out;
    }

    public function touchExistingFromScan(
        Jubelioorder $order,
        User $user,
        Jubeliosync $sync,
        string|int|null $resolvedJubelioOrderId = null,
    ): void {
        if ($order->type !== 'SELL') {
            return;
        }

        $order->order_status = JubelioOrderReadyToShip::STATUS;
        $order->user_id = (int) $user->id;

        if ((int) $sync->warehouse_id > 0) {
            $order->warehouse_id = (int) $sync->warehouse_id;
            $order->jubelio_store_id = (int) $sync->jubelio_store_id;
            $order->jubelio_location_id = (int) $sync->jubelio_location_id;
        }

        if ($this->shouldReplacePlaceholderJubelioId($order->jubelio_order_id, $resolvedJubelioOrderId)) {
            $order->jubelio_order_id = (string) $resolvedJubelioOrderId;
        }

        if ($order->status !== 0) {
            $order->status = 0;
            $order->error = null;
            $order->error_type = null;
        }

        $order->save();
    }

    private function shouldReplacePlaceholderJubelioId(mixed $current, string|int|null $resolved): bool
    {
        if ($resolved === null || trim((string) $resolved) === '') {
            return false;
        }

        $currentStr = trim((string) $current);
        if ($currentStr === '') {
            return true;
        }

        if (ShopeeJubelioQueuePrecreator::isPlaceholderJubelioOrderId($currentStr)) {
            return true;
        }

        return str_starts_with(strtolower($currentStr), 'mobile-scan:');
    }

    /**
     * @return array{invoice: string, jubelio_order_id: string|int|null}
     */
    public function resolveInvoiceForBarcode(string $marketplaceKey, string $rawBarcode, int $jubelioStoreId): array
    {
        if ($this->invoiceResolver->marketplaceResolvesViaTracking($marketplaceKey)) {
            $resolved = $this->invoiceResolver->resolveInvoice($marketplaceKey, $rawBarcode, $jubelioStoreId);
            if ($resolved === null) {
                if (! (bool) config('services.jubelio.active', false)) {
                    throw new InvalidArgumentException('Jubelio tidak aktif — tidak bisa mencari nomor resi.');
                }

                $reason = $this->invoiceResolver->lastLookupMessage()
                    ?? 'Resi tidak ditemukan di Jubelio.';

                throw new InvalidArgumentException($reason);
            }

            return [
                'invoice' => $resolved['invoice'],
                'jubelio_order_id' => $resolved['salesorder_id'],
            ];
        }

        return [
            'invoice' => $this->formatInvoice($marketplaceKey, $rawBarcode, $jubelioStoreId),
            'jubelio_order_id' => null,
        ];
    }

    public function placeholderJubelioOrderId(string $invoice, string|int|null $jubelioSalesOrderId = null): string
    {
        if ($jubelioSalesOrderId !== null && trim((string) $jubelioSalesOrderId) !== '') {
            return (string) $jubelioSalesOrderId;
        }

        return 'mobile-scan:'.Str::lower($invoice);
    }

    public function resolveMarketplaceKeyForStore(string $storeName): ?string
    {
        $needle = strtolower(trim($storeName));
        if ($needle === '') {
            return null;
        }

        foreach ($this->marketplacesConfig() as $key => $row) {
            foreach ($row['store_name_patterns'] as $pattern) {
                if (str_contains($needle, strtolower($pattern))) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function shopeeOrderSnFromPrefixedInvoice(string $invoice): ?string
    {
        $invoice = trim($invoice);
        if ($invoice === '') {
            return null;
        }

        foreach ($this->shopeeInvoicePrefix->allKnownPrefixes() as $prefix) {
            $quoted = preg_quote($prefix, '/');
            if (preg_match('/^'.$quoted.'-(.+)$/i', $invoice, $matches) === 1) {
                $orderSn = trim($matches[1]);

                return $orderSn !== '' ? $orderSn : null;
            }
        }

        return null;
    }
}
