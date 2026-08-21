<?php

namespace App\Services\Jubelio;

class JubelioOrderPayloadPresenter
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function summary(array $data): array
    {
        $items = $data['items'] ?? [];

        return [
            'transaction_date' => $data['transaction_date'] ?? $data['created_date'] ?? null,
            'store_name' => $data['source_name'] ?? $data['store_name'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'real_total' => $data['escrow_amount'] ?? $data['real_total'] ?? null,
            'sub_total' => $data['sub_total'] ?? null,
            'item_count' => count($items),
            'customer_name' => $data['customer_name'] ?? ($data['ship_to']['name'] ?? null),
            'payment_method' => $data['payment_method'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{item_code: string, quantity: float|int, price: float|int|null}>
     */
    public static function items(array $data): array
    {
        return collect($data['items'] ?? [])
            ->map(function (array $item): array {
                return [
                    'item_code' => (string) ($item['item_code'] ?? $item['sku'] ?? '—'),
                    'quantity' => $item['qty'] ?? $item['qty_in_base'] ?? 0,
                    'price' => $item['price'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
