<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jubelioorder extends Model
{
    /** @use HasFactory<\Database\Factories\JubelioorderFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, mixed>
     */
    public function payloadArray(): array
    {
        if (is_array($this->payload)) {
            return $this->payload;
        }

        $decoded = json_decode($this->payload ?? '', true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadSummary(): array
    {
        $data = $this->payloadArray();
        $items = $data['items'] ?? [];

        return [
            'transaction_date' => $data['transaction_date'] ?? $data['created_date'] ?? null,
            'store_name' => $data['source_name'] ?? $data['store_name'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'grand_total' => $data['grand_total'] ?? null,
            'sub_total' => $data['sub_total'] ?? null,
            'item_count' => count($items),
            'customer_name' => $data['customer_name'] ?? ($data['ship_to']['name'] ?? null),
            'payment_method' => $data['payment_method'] ?? null,
        ];
    }

    /**
     * @return list<array{item_code: string, quantity: float|int, price: float|int|null}>
     */
    public function payloadItems(): array
    {
        return collect($this->payloadArray()['items'] ?? [])
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

    public function transactionsSearchUrl(): string
    {
        return route('transactions.index', ['invoice_number' => $this->invoice]);
    }

    /**
     * Get the user that executed the order.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'execute_by');
    }

    /**
     * Get the transaction associated with the jubelio order.
     */
    public function trx(): HasOne
    {
        return $this->hasOne(Transaction::class, 'invoice_number', 'invoice');
    }
}
