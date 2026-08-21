<?php

namespace App\Services\Jubelio;

use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Transaction;
use App\Models\WarehouseItem;

class JubelioOrderShowPresenter
{
    public function __construct(
        private JubelioOrderWarehouseResolver $warehouseResolver,
    ) {}

    /**
     * @return array{
     *     parties: array{
     *         warehouse: ?array{id: int, name: string, url: string},
     *         customer: ?array{id: int, name: string, url: string},
     *         warehouse_id: ?int
     *     },
     *     items: list<array<string, mixed>>
     * }
     */
    public function present(Jubelioorder $order): array
    {
        $parties = $this->resolveParties($order);
        $items = $this->enrichItems($order->payloadItems(), $parties['warehouse_id']);

        return [
            'parties' => $parties,
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     warehouse: ?array{id: int, name: string, url: string},
     *     customer: ?array{id: int, name: string, url: string},
     *     warehouse_id: ?int
     * }
     */
    public function resolveParties(Jubelioorder $order): array
    {
        $warehouse = null;
        $customer = null;

        if ($order->relationLoaded('trx') && $order->trx) {
            if ($order->type === 'RETURN') {
                $warehouse = Addrbook::find($order->trx->receiver_id);
                $customer = Addrbook::find($order->trx->sender_id);
            } else {
                $warehouse = Addrbook::find($order->trx->sender_id);
                $customer = Addrbook::find($order->trx->receiver_id);
            }
        }

        if (! $warehouse || ! $customer) {
            if ($order->type === 'RETURN') {
                $payload = $order->payloadArray();
                $sell = Transaction::query()
                    ->where('type', Transaction::TYPE_SELL)
                    ->where('invoice', $payload['salesorder_no'] ?? '')
                    ->first();

                if ($sell) {
                    $warehouse ??= Addrbook::find($sell->sender_id);
                    $customer ??= Addrbook::find($sell->receiver_id);
                }
            } else {
                $syncIndex = $this->warehouseResolver->syncIndex();
                $payload = $order->payloadArray();
                $storeId = (int) ($payload['store_id'] ?? 0);
                $locationId = (int) ($payload['location_id'] ?? 0);

                if ($storeId > 0 && $locationId > 0) {
                    $sync = $syncIndex->get("{$storeId}:{$locationId}");
                    if ($sync) {
                        $warehouse ??= $sync->warehouse ?? Addrbook::find($sync->warehouse_id);
                        $customer ??= $sync->customer ?? Addrbook::find($sync->customer_id);
                    }
                }
            }
        }

        return [
            'warehouse' => $this->partyLink($warehouse),
            'customer' => $this->partyLink($customer),
            'warehouse_id' => $warehouse?->id,
        ];
    }

    /**
     * @param  list<array{item_code: string, quantity: float|int, price: float|int|null}>  $payloadItems
     * @return list<array<string, mixed>>
     */
    public function enrichItems(array $payloadItems, ?int $warehouseId): array
    {
        if ($payloadItems === []) {
            return [];
        }

        $codes = collect($payloadItems)->pluck('item_code')->unique()->all();
        $itemsByCode = Item::findManyBySkus($codes);

        $warehouseStock = [];
        if ($warehouseId) {
            $itemIds = $itemsByCode->pluck('id')->all();
            if ($itemIds !== []) {
                $warehouseStock = WarehouseItem::query()
                    ->where('warehouse_id', $warehouseId)
                    ->whereIn('item_id', $itemIds)
                    ->pluck('quantity', 'item_id')
                    ->all();
            }
        }

        return collect($payloadItems)->map(function (array $row) use ($itemsByCode, $warehouseStock): array {
            $code = strtoupper((string) $row['item_code']);
            $item = $itemsByCode[$code] ?? null;

            $enriched = $row;
            $enriched['item_id'] = $item?->id;
            $enriched['item_name'] = $item?->name;
            $enriched['item_url'] = $item ? $this->itemShowUrl($item) : null;
            $enriched['aria_stock'] = $item && isset($warehouseStock[$item->id])
                ? (float) $warehouseStock[$item->id]
                : ($item ? 0.0 : null);

            return $enriched;
        })->values()->all();
    }

  /**
     * @return ?array{id: int, name: string, url: string}
     */
    private function partyLink(?Addrbook $party): ?array
    {
        if (! $party) {
            return null;
        }

        return [
            'id' => $party->id,
            'name' => $party->name,
            'url' => route('addrbook.type.show', ['type' => $party->type_slug, 'addrbook' => $party->id]),
        ];
    }

    private function itemShowUrl(Item $item): string
    {
        return $item->type === ItemType::ASSET_LANCAR
            ? route('assetlancar.show', $item)
            : route('items.show', $item);
    }
}
