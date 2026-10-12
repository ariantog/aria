<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use App\Services\JubelioService;
use Illuminate\Support\Facades\DB;

class JubelioOrderPayloadService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $cache = [];

    public function __construct(private JubelioService $jubelioService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(Jubelioorder $order, bool $allowApi = true): ?array
    {
        if ($order->id !== null && array_key_exists($order->id, $this->cache)) {
            return $this->cache[$order->id];
        }

        $stored = $this->decodeStored($order);
        if ($stored !== null) {
            if ($order->id !== null) {
                $this->cache[$order->id] = $stored;
            }

            return $stored;
        }

        if (! $allowApi) {
            return null;
        }

        $result = $this->fetchFromApi($order);
        if ($result !== null && $order->id !== null) {
            $this->persist($order, $result, touchTimestamps: false);
            $this->cache[$order->id] = $result;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchOrEmpty(Jubelioorder $order, bool $allowApi = true): array
    {
        return $this->fetch($order, $allowApi) ?? [];
    }

    /**
     * Force a fresh API read and overwrite stored payload (Refresh payload).
     *
     * @return array<string, mixed>|null
     */
    public function refreshFromApi(Jubelioorder $order): ?array
    {
        $this->forget($order->id);
        Jubelioorder::clearPayloadCacheFor($order->id);

        $result = $this->fetchFromApi($order);
        if ($result !== null && $order->id !== null) {
            $this->persist($order, $result, touchTimestamps: false);
            $this->cache[$order->id] = $result;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persist(Jubelioorder $order, array $payload, bool $touchTimestamps = true): void
    {
        if ($order->id === null) {
            return;
        }

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        if ($touchTimestamps) {
            $order->forceFill(['payload' => $encoded])->save();
        } else {
            DB::table('jubelioorders')
                ->where('id', $order->id)
                ->update(['payload' => $encoded]);
            $order->setAttribute('payload', $encoded);
        }

        Jubelioorder::clearPayloadCacheFor($order->id);
        $this->cache[$order->id] = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeStored(Jubelioorder $order): ?array
    {
        $raw = $order->getAttributes()['payload'] ?? $order->payload ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_array($raw)) {
            return $raw !== [] ? $raw : null;
        }

        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    public function forget(?int $orderId): void
    {
        if ($orderId !== null) {
            unset($this->cache[$orderId]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchFromApi(Jubelioorder $order): ?array
    {
        $id = $order->jubelio_order_id;
        if ($id === null || $id === '') {
            return null;
        }

        if ($order->type === 'RETURN') {
            return $this->jubelioService->fetchSalesReturn($id);
        }

        return $this->jubelioService->fetchSalesOrder($id);
    }
}
