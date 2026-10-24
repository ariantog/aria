<?php

namespace App\Services\Jubelio;

use App\Models\Jubeliosync;
use App\Services\JubelioService;

/**
 * Loads Jubelio locations / store-channel rows and compares them to jubeliosyncs.
 */
class JubelioSyncCatalogService
{
    public function __construct(private JubelioService $jubelioService) {}

    /**
     * @return list<array{location_id: int, location_name: string}>
     */
    public function fetchAllLocations(): array
    {
        $locations = [];
        $page = 1;
        $pageSize = 200;

        do {
            $response = $this->jubelioService->get('https://api2.jubelio.com/locations/', [
                'page' => $page,
                'pageSize' => $pageSize,
            ]);

            if (! $response || ! $response->successful()) {
                break;
            }

            $chunk = $response->json('data') ?? [];
            if ($chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                $locationId = (int) ($row['location_id'] ?? 0);
                if (! Jubeliosync::isMappedLocationId($locationId)) {
                    continue;
                }

                $locations[] = [
                    'location_id' => $locationId,
                    'location_name' => (string) ($row['location_name'] ?? ''),
                ];
            }

            $page++;
        } while (count($chunk) >= $pageSize);

        return $locations;
    }

    /**
     * @return list<array{store_id: int, store_name: string}>|null null when API failed
     */
    public function fetchStoresForLocation(int $locationId): ?array
    {
        $response = $this->jubelioService->get('https://api2.jubelio.com/locations/'.$locationId);

        if (! $response || ! $response->successful()) {
            return null;
        }

        return $this->extractStoresFromLocationPayload($response->json());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{store_id: int, store_name: string}>
     */
    public function extractStoresFromLocationPayload(array $payload): array
    {
        $rows = $payload['stores'] ?? ($payload['warehouse'] ?? []);
        if (! is_array($rows)) {
            return [];
        }

        $stores = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $storeId = (int) ($row['store_id'] ?? 0);
            if (! Jubeliosync::isMappedStoreId($storeId)) {
                continue;
            }

            $stores[] = [
                'store_id' => $storeId,
                'store_name' => (string) ($row['store_name'] ?? ''),
            ];
        }

        return $stores;
    }

    /**
     * @return array<string, int> keys "storeId:locationId" => jubeliosync id
     */
    public function mappedStoreLocationKeys(): array
    {
        $keys = [];

        Jubeliosync::query()
            ->select(['id', 'jubelio_store_id', 'jubelio_location_id'])
            ->whereMappedJubelioLocation()
            ->each(function (Jubeliosync $sync) use (&$keys) {
                if (! Jubeliosync::hasMappedStoreLocationPair(
                    (int) $sync->jubelio_store_id,
                    (int) $sync->jubelio_location_id
                )) {
                    return;
                }

                $keys[$this->pairKey((int) $sync->jubelio_store_id, (int) $sync->jubelio_location_id)] = (int) $sync->id;
            });

        return $keys;
    }

    /**
     * Stores present in Jubelio but not yet in jubeliosyncs.
     *
     * @return list<array{
     *     location_id: int,
     *     location_name: string,
     *     store_id: int,
     *     store_name: string
     * }>
     */
    public function discoverUnmappedStores(?callable $progress = null): array
    {
        $mapped = $this->mappedStoreLocationKeys();
        $unmapped = [];

        $locationIds = Jubeliosync::query()
            ->whereMappedJubelioLocation()
            ->distinct()
            ->pluck('jubelio_location_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($locationIds as $locationId) {
            $stores = $this->fetchStoresForLocation($locationId);
            if ($stores === null) {
                continue;
            }

            $locationName = (string) (Jubeliosync::query()
                ->where('jubelio_location_id', $locationId)
                ->value('jubelio_location_name') ?? '');

            foreach ($stores as $store) {
                $key = $this->pairKey($store['store_id'], $locationId);
                if (isset($mapped[$key])) {
                    continue;
                }

                $unmapped[] = [
                    'location_id' => $locationId,
                    'location_name' => $locationName,
                    'store_id' => $store['store_id'],
                    'store_name' => $store['store_name'],
                ];
            }

            if ($progress) {
                $progress($locationId);
            }
        }

        usort($unmapped, fn (array $a, array $b) => [$a['location_name'], $a['store_name']] <=> [$b['location_name'], $b['store_name']]);

        return $unmapped;
    }

    public function pairKey(int $storeId, int $locationId): string
    {
        return $storeId.':'.$locationId;
    }
}
