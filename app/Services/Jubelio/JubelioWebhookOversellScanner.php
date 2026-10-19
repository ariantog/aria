<?php

namespace App\Services\Jubelio;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\WarehouseItem;
use App\Services\Lark\OperationalAlertDispatcher;

/**
 * Read-only oversell probe for inbound webhook payloads (before queue insert).
 */
class JubelioWebhookOversellScanner
{
    public function __construct(
        protected OperationalAlertDispatcher $alerts,
        protected JubelioOrderWarehouseResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function scanAndNotify(array $payload): void
    {
        if (! config('lark.enabled')) {
            return;
        }

        $invoice = (string) ($payload['salesorder_no'] ?? '');
        if ($invoice === '') {
            return;
        }

        $warehouseColumns = $this->resolver->sellWarehouseColumnsFromPayload($payload);
        $warehouseId = (int) ($warehouseColumns['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
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
                    'code' => $row['code'],
                    'available' => $available,
                    'needed' => $needed,
                ];
            }
        }

        if ($insufficient === []) {
            return;
        }

        $warehouseName = Addrbook::query()->find($warehouseId)?->name ?? (string) $warehouseId;
        $summary = collect($insufficient)
            ->map(fn (array $r) => $r['code'].' (avail '.$r['available'].', need '.$r['needed'].')')
            ->implode('; ');

        $fingerprint = 'oversell:webhook:'.hash('sha256', $invoice.':'.$warehouseId.':'.$summary);

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
}
