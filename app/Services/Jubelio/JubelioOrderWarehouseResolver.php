<?php

namespace App\Services\Jubelio;

use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use Illuminate\Support\Collection;

class JubelioOrderWarehouseResolver
{
    /**
     * @return Collection<string, Jubeliosync>
     */
    public function syncIndex(): Collection
    {
        return Jubeliosync::query()
            ->with('warehouse')
            ->get()
            ->keyBy(fn (Jubeliosync $sync) => $this->key((int) $sync->jubelio_store_id, (int) $sync->jubelio_location_id));
    }

    /**
     * @return array{jubelio_warehouse: ?string, aria_warehouse: ?string}
     */
    public function resolve(Jubelioorder $order, ?Collection $syncIndex = null): array
    {
        $payload = $order->payloadArray();
        $storeId = (int) ($payload['store_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);

        $sync = null;
        if ($storeId > 0 && $locationId > 0) {
            $index = $syncIndex ?? $this->syncIndex();
            $sync = $index->get($this->key($storeId, $locationId));
        }

        return [
            'jubelio_warehouse' => $sync?->jubelio_location_name
                ?? ($payload['location_name'] ?? null),
            'aria_warehouse' => $sync?->warehouse?->name,
        ];
    }

    private function key(int $storeId, int $locationId): string
    {
        return "{$storeId}:{$locationId}";
    }
}
