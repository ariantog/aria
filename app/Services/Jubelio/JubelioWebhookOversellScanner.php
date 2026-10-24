<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\OperationalAlert;
use App\Models\WarehouseItem;
use App\Services\Lark\LarkAlertLinkBuilder;
use App\Services\Lark\OperationalAlertDispatcher;

/**
 * Early oversell probe on inbound webhooks (pre-ship statuses only).
 *
 * SHIPPED/COMPLETED payloads must not use warehouse_item qty vs order qty — stock is
 * already consumed when Aria posted the sell, and Jubelio resends those webhooks often.
 * Post-time checks live in ProcessJubelioOrder::validateWarehouseStock.
 */
class JubelioWebhookOversellScanner
{
    /** @var list<string> */
    private const POST_SHIP_WEBHOOK_STATUSES = ['SHIPPED', 'COMPLETED', 'RETURNED'];
    public function __construct(
        protected OperationalAlertDispatcher $alerts,
        protected JubelioOrderWarehouseResolver $resolver,
        protected LarkAlertLinkBuilder $links,
        protected JubelioSellInvoiceGuard $invoiceGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function scanAndNotify(array $payload): void
    {
        if (! config('lark.enabled')) {
            return;
        }

        if (! app(\App\Services\Lark\LarkSettingsService::class)->eventSendEnabled('jubelio.oversell.webhook')) {
            return;
        }

        $invoice = trim((string) ($payload['salesorder_no'] ?? ''));
        if ($invoice === '') {
            return;
        }

        if ($this->isTerminalWebhookStatus($payload)) {
            return;
        }

        $warehouseColumns = $this->resolver->sellWarehouseColumnsFromPayload($payload);
        $warehouseId = (int) ($warehouseColumns['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            return;
        }

        if ($this->sellAlreadyFulfilledInAria($invoice)) {
            $this->rearmWhenClear($invoice, $warehouseId);

            return;
        }

        if ($this->isPostShipWebhookStatus($payload)) {
            $this->rearmWhenClear($invoice, $warehouseId);

            return;
        }

        $lines = $payload['items'] ?? $payload['order_items'] ?? [];
        if (! is_array($lines) || $lines === []) {
            return;
        }

        $matched = $this->matchLines($lines);
        if ($matched['error'] !== null) {
            return;
        }

        $insufficient = [];
        foreach ($matched['items'] as $row) {
            $available = (float) (WarehouseItem::query()
                ->where('warehouse_id', $warehouseId)
                ->where('item_id', $row['itemId'])
                ->value('quantity') ?? 0);
            $needed = (float) $row['quantity'];
            if ($needed > $available) {
                $insufficient[] = [
                    'item_id' => $row['itemId'],
                    'code' => $row['code'],
                    'available' => $available,
                    'needed' => $needed,
                ];
            }
        }

        if ($insufficient === []) {
            $this->rearmWhenClear($invoice, $warehouseId);

            return;
        }

        $warehouseName = Addrbook::query()->find($warehouseId)?->name ?? (string) $warehouseId;
        $summary = collect($insufficient)
            ->map(fn (array $r) => $r['code'].' (avail '.$r['available'].', need '.$r['needed'].')')
            ->implode('; ');

        // One Lark card per invoice + warehouse while oversell persists. Do not hash avail/need —
        // they change on every webhook retry and caused thousands of duplicate notifications.
        $fingerprint = $this->webhookFingerprint($invoice, $warehouseId);

        $firstItemId = (int) ($insufficient[0]['item_id'] ?? 0);
        $salesorderId = isset($payload['salesorder_id']) ? (string) $payload['salesorder_id'] : null;

        $this->alerts->dispatch(
            'jubelio.oversell.webhook',
            $fingerprint,
            'warning',
            'Potensi oversell (webhook Jubelio)',
            [
                'Invoice' => $invoice,
                'Gudang' => $warehouseName,
                'Detail' => $summary,
                'Status webhook' => (string) ($payload['status'] ?? ''),
                '_actions' => $this->links->jubelioOversellActions($invoice, $salesorderId, $firstItemId > 0 ? $firstItemId : null),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $arrayItems
     * @return array{error: ?string, items: list<array{itemId: int, code: string, quantity: float}>}
     */
    protected function matchLines(array $arrayItems): array
    {
        $jubelioIds = collect($arrayItems)
            ->map(fn (array $line) => (int) ($line['item_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->all();

        $existingProducts = Item::findManyByJubelioItemIds($jubelioIds);
        $items = [];

        foreach ($arrayItems as $line) {
            $jubelioItemId = (int) ($line['item_id'] ?? 0);
            if ($jubelioItemId <= 0) {
                continue;
            }

            $product = $existingProducts->get($jubelioItemId);
            if (! $product) {
                continue;
            }

            $qty = JubelioOrderLineQuantity::resolve($line, 'qty');
            $items[] = [
                'itemId' => (int) $product->id,
                'code' => (string) $product->code,
                'quantity' => $qty,
            ];
        }

        if ($items === []) {
            return ['error' => 'no matched lines', 'items' => []];
        }

        return ['error' => null, 'items' => $items];
    }

    public function webhookFingerprint(string $invoice, int $warehouseId): string
    {
        return 'oversell:webhook:'.hash('sha256', $invoice.':'.$warehouseId);
    }

    private function rearmWhenClear(string $invoice, int $warehouseId): void
    {
        OperationalAlert::query()
            ->where('fingerprint', $this->webhookFingerprint($invoice, $warehouseId))
            ->where('status', 'open')
            ->update([
                'status' => 'rearmed',
                'rearmed_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isTerminalWebhookStatus(array $payload): bool
    {
        $status = $this->normalizedWebhookStatus($payload);
        if ($status === '') {
            return false;
        }

        return in_array($status, ['CANCELED', 'CANCELLED', 'VOID', 'VOIDED'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isPostShipWebhookStatus(array $payload): bool
    {
        $status = $this->normalizedWebhookStatus($payload);

        return $status !== '' && in_array($status, self::POST_SHIP_WEBHOOK_STATUSES, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function normalizedWebhookStatus(array $payload): string
    {
        return strtoupper(trim((string) ($payload['status'] ?? '')));
    }

    /**
     * After Aria posts the sell, warehouse qty already reflects this order — comparing
     * webhook need vs current stock would false-positive as oversell.
     */
    private function sellAlreadyFulfilledInAria(string $invoice): bool
    {
        if ($this->invoiceGuard->sellInvoiceInTransactions($invoice)) {
            return true;
        }

        return Jubelioorder::query()
            ->where('type', 'SELL')
            ->where('invoice', $invoice)
            ->where('status', 2)
            ->where('error_type', JubelioOrderSyncStatus::SUCCESS)
            ->exists();
    }
}
