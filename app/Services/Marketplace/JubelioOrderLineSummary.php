<?php

namespace App\Services\Marketplace;

use App\Models\Item;
use App\Services\Jubelio\JubelioOrderLineQuantity;
use Illuminate\Support\Collection;

/**
 * Build read-only Jubelio line summary using the same item_id linkage as ProcessJubelioOrder.
 */
class JubelioOrderLineSummary
{
    /**
     * @param  array<string, mixed>|null  $payload  Jubelio sales order API body
     * @return array{
     *     lines: list<array{jubelio_item_id: int, aria_item_id: ?int, code: ?string, qty: float, amount: float}>,
     *     line_count: int,
     *     unlinked: list<string>
     * }
     */
    public function fromPayload(?array $payload, string $qtyKey = 'qty'): array
    {
        $rawItems = is_array($payload) ? ($payload['items'] ?? []) : [];
        if (! is_array($rawItems)) {
            $rawItems = [];
        }

        $jubelioIds = collect($rawItems)
            ->map(fn ($line) => is_array($line) ? (int) ($line['item_id'] ?? 0) : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int, Item> $byJubelioId */
        $byJubelioId = Item::findManyByJubelioItemIds($jubelioIds);

        $lines = [];
        $unlinked = [];

        foreach ($rawItems as $line) {
            if (! is_array($line)) {
                continue;
            }

            $jubelioItemId = (int) ($line['item_id'] ?? 0);
            if ($jubelioItemId <= 0) {
                $code = (string) ($line['item_code'] ?? $line['sku'] ?? '?');
                $unlinked[] = $code.' (item_id kosong)';

                continue;
            }

            $product = $byJubelioId->get($jubelioItemId);
            if (! $product) {
                $code = (string) ($line['item_code'] ?? $line['sku'] ?? (string) $jubelioItemId);
                $unlinked[] = $code.' (Jubelio item_id '.$jubelioItemId.' belum terhubung)';

                continue;
            }

            $qty = JubelioOrderLineQuantity::resolve($line, $qtyKey);
            $amount = (float) ($line['amount'] ?? $line['subtotal'] ?? 0);
            if ($amount <= 0 && $qty > 0) {
                $price = (float) ($line['price'] ?? 0);
                $amount = round($price * $qty, 2);
            }

            $lines[] = [
                'jubelio_item_id' => $jubelioItemId,
                'aria_item_id' => (int) $product->id,
                'code' => (string) $product->code,
                'qty' => $qty,
                'amount' => $amount,
            ];
        }

        return [
            'lines' => $lines,
            'line_count' => count($lines),
            'unlinked' => $unlinked,
        ];
    }
}