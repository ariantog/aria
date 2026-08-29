<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use App\Services\JubelioService;

class JubelioOrderPayloadService
{
    /** @var array<int, array<string, mixed>|null> */
    private array $cache = [];

    public function __construct(private JubelioService $jubelioService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(Jubelioorder $order): ?array
    {
        if ($order->id !== null && array_key_exists($order->id, $this->cache)) {
            return $this->cache[$order->id];
        }

        $id = $order->jubelio_order_id;
        if ($id === null || $id === '') {
            $result = null;
        } elseif ($order->type === 'RETURN') {
            $result = $this->jubelioService->fetchSalesReturn($id);
        } else {
            $result = $this->jubelioService->fetchSalesOrder($id);
        }

        if ($order->id !== null) {
            $this->cache[$order->id] = $result;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchOrEmpty(Jubelioorder $order): array
    {
        return $this->fetch($order) ?? [];
    }

    public function forget(?int $orderId): void
    {
        if ($orderId !== null) {
            unset($this->cache[$orderId]);
        }
    }
}
