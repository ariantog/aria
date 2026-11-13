<?php

namespace App\Models;

use App\Models\Transaction;
use App\Services\Jubelio\JubelioOrderPayloadPresenter;
use App\Services\Jubelio\JubelioOrderReadyToShip;
use App\Services\Jubelio\JubelioOrderPayloadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Jubelioorder extends Model
{
    /** @use HasFactory<\Database\Factories\JubelioorderFactory> */
    use HasFactory;

    /** @var array<int, array<string, mixed>> */
    private static array $resolvedPayloadCache = [];

    protected $guarded = [];

    protected $casts = [
        'stock_error_items' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (Jubelioorder $order): void {
            // Payload is fetched from Jubelio API (JubelioOrderPayloadService).
            // Legacy L10 rows may still have JSON here; never persist it on save.
            if ($order->payload !== null && $order->payload !== '') {
                $order->payload = null;
            }
        });
    }

    public static function clearPayloadCache(): void
    {
        self::$resolvedPayloadCache = [];
    }

    public static function clearPayloadCacheFor(?int $orderId): void
    {
        if ($orderId !== null) {
            unset(self::$resolvedPayloadCache[$orderId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadArray(): array
    {
        if ($this->id === null) {
            return [];
        }

        if (isset(self::$resolvedPayloadCache[$this->id])) {
            return self::$resolvedPayloadCache[$this->id];
        }

        $data = app(JubelioOrderPayloadService::class)->fetchOrEmpty($this);
        self::$resolvedPayloadCache[$this->id] = $data;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadSummary(): array
    {
        return JubelioOrderPayloadPresenter::summary($this->payloadArray());
    }

    /**
     * @return list<array{item_code: string, quantity: float|int, price: float|int|null}>
     */
    public function payloadItems(): array
    {
        return JubelioOrderPayloadPresenter::items($this->payloadArray());
    }

    /**
     * @return list<array{item_id: int, code: string, available: float, needed: float}>
     */
    public function stockErrorItemsList(): array
    {
        $items = $this->stock_error_items;

        return is_array($items) ? $items : [];
    }

    public function hasStockError(): bool
    {
        return $this->stockErrorItemsList() !== [];
    }

    public function transactionsSearchUrl(): string
    {
        return route('transactions.index', ['invoice' => $this->invoice]);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 2 && $this->error_type === 10;
    }

    public function canProcessManually(): bool
    {
        return in_array($this->type, ['SELL', 'RETURN'], true)
            && ! $this->isSuccessful()
            && ! $this->isPermanentlySkipped()
            && ! JubelioOrderReadyToShip::isHeldForCourier($this);
    }

    public function sourceLabel(): string
    {
        return JubelioOrderReadyToShip::sourceLabel((int) $this->source);
    }

    public function scannedByLabel(): string
    {
        if ((int) $this->user_id > 0) {
            return $this->scanner?->name
                ?? $this->scanner?->username
                ?? 'User #'.$this->user_id;
        }

        return 'Webhook';
    }

    public function isPermanentlySkipped(): bool
    {
        return $this->status === 2
            && $this->error_type === \App\Services\Jubelio\JubelioOrderSyncStatus::ERROR_SKIPPED;
    }

    public function canMarkSolved(): bool
    {
        return ! $this->isSuccessful()
            && ($this->status === 1 || ($this->status === 2 && $this->error_type === 2));
    }

    /**
     * Queue row may be removed when no Aria transaction was posted for this invoice.
     */
    public function canDeleteFromQueue(): bool
    {
        if (trim((string) $this->invoice) === '') {
            return false;
        }

        return ! Transaction::query()
            ->where('invoice', $this->invoice)
            ->exists();
    }

    /**
     * User who ran manual Process / cron execute_by (legacy relation name).
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'execute_by');
    }

    /**
     * User who queued the order via mobile resi scan (when applicable).
     */
    public function scanner(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    /**
     * Get the transaction associated with the jubelio order.
     */
    public function trx(): HasOne
    {
        return $this->hasOne(Transaction::class, 'invoice', 'invoice');
    }
}
