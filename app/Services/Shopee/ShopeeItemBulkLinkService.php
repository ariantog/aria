<?php

namespace App\Services\Shopee;

use App\Models\Item;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

class ShopeeItemBulkLinkService
{
    private const SESSION_KEY = 'shopee_bulk_link_preview';

    public const PREVIEW_DISPLAY_LIMIT = 150;

    public function __construct(
        private ShopeeItemBulkLinkParser $parser,
        private ShopeeItemLinkApplier $applier,
        private ShopeeStockApiService $stockApi,
    ) {}

    /**
     * @return array{
     *     token: string,
     *     rows: list<array<string, mixed>>,
     *     summary: array{total: int, ready: int, skipped: int, errors: int},
     * }
     */
    public function preview(UploadedFile $file): array
    {
        $parsed = $this->parser->parse((string) $file->getRealPath());
        $previewRows = $this->evaluateRows($parsed, dryRun: true);

        $token = bin2hex(random_bytes(16));
        Cache::put(self::SESSION_KEY.':'.$token, [
            'filename' => $file->getClientOriginalName(),
            'rows' => $parsed,
        ], now()->addHour());

        return [
            'token' => $token,
            'rows' => array_slice($previewRows, 0, self::PREVIEW_DISPLAY_LIMIT),
            'rows_total' => count($previewRows),
            'rows_truncated' => count($previewRows) > self::PREVIEW_DISPLAY_LIMIT,
            'summary' => $this->summarize($previewRows),
        ];
    }

    /**
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summary: array{total: int, ready: int, linked: int, skipped: int, errors: int},
     * }
     */
    public function apply(string $token, bool $overwriteExisting = false): array
    {
        $payload = Cache::pull(self::SESSION_KEY.':'.$token);
        if (! is_array($payload) || ! is_array($payload['rows'] ?? null)) {
            throw new \InvalidArgumentException('Preview kedaluwarsa — upload ulang file.');
        }

        $resultRows = $this->evaluateRows($payload['rows'], dryRun: false, overwriteExisting: $overwriteExisting);

        return [
            'filename' => (string) ($payload['filename'] ?? ''),
            'rows' => array_slice($resultRows, 0, self::PREVIEW_DISPLAY_LIMIT),
            'rows_total' => count($resultRows),
            'rows_truncated' => count($resultRows) > self::PREVIEW_DISPLAY_LIMIT,
            'summary' => $this->summarize($resultRows, applied: true),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $parsedRows
     * @return list<array<string, mixed>>
     */
    private function evaluateRows(array $parsedRows, bool $dryRun, bool $overwriteExisting = false): array
    {
        $lookup = $this->loadItemLookupMaps($parsedRows);
        $itemsById = $this->loadItemsById($parsedRows);

        $shopeeIds = collect($parsedRows)
            ->pluck('shopee_item_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        $modelsByShopeeItem = [];
        if (! $dryRun && $shopeeIds !== [] && $this->stockApi->isReady()) {
            $modelsByShopeeItem = $this->stockApi->modelsByItemIds($shopeeIds);
        } elseif (! $dryRun && $shopeeIds !== [] && ! $this->stockApi->isReady()) {
            return array_map(fn (array $row) => $row + [
                'status' => 'error',
                'message' => 'STOCK CHECKER OAuth belum siap.',
                'item' => null,
            ], $parsedRows);
        }

        $out = [];
        foreach ($parsedRows as $row) {
            $out[] = $this->evaluateRow(
                $row,
                $lookup['legacy'],
                $lookup['code'],
                $itemsById,
                $modelsByShopeeItem,
                $dryRun,
                $overwriteExisting,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Item>  $legacyMap
     * @param  array<string, Item>  $codeMap
     * @param  array<int, Item>  $itemsById
     * @param  array<int, list<array<string, mixed>>>  $modelsByShopeeItem
     * @return array<string, mixed>
     */
    private function evaluateRow(
        array $row,
        array $legacyMap,
        array $codeMap,
        array $itemsById,
        array $modelsByShopeeItem,
        bool $dryRun,
        bool $overwriteExisting,
    ): array {
        $code = isset($row['code']) ? trim((string) $row['code']) : '';
        $ariaItemId = (int) ($row['aria_item_id'] ?? 0);
        $shopeeItemId = (int) ($row['shopee_item_id'] ?? 0);
        $shopeeModelId = (int) ($row['shopee_model_id'] ?? 0);

        $item = null;
        $matchedVia = null;
        if ($ariaItemId > 0) {
            $item = $itemsById[$ariaItemId] ?? null;
            $matchedVia = $item !== null ? 'aria_item_id' : null;
        }
        if ($item === null && $code !== '') {
            $resolved = $this->resolveItemBySku($code, $legacyMap, $codeMap);
            if ($resolved !== null) {
                $item = $resolved['item'];
                $matchedVia = $resolved['matched_via'];
            }
        }

        if ($item === null) {
            return $row + [
                'status' => 'error',
                'message' => $code !== ''
                    ? 'SKU tidak ditemukan (legacy_code lalu code).'
                    : 'SKU kolom 3 kosong.',
                'item' => null,
            ];
        }

        if ($shopeeModelId <= 0) {
            return $row + [
                'status' => 'error',
                'message' => 'Kode Variasi (kolom 2) kosong atau tidak valid.',
                'item' => $this->itemSnapshot($item),
            ];
        }

        if ($shopeeItemId <= 0) {
            $existingItemId = (int) ($item->shopee_item_id ?? 0);
            if ($existingItemId > 0) {
                $shopeeItemId = $existingItemId;
            } else {
                return $row + [
                    'status' => 'error',
                    'message' => 'Kode Produk (kolom 1) kosong — diperlukan untuk SKU yang belum pernah di-link.',
                    'item' => $this->itemSnapshot($item),
                ];
            }
        }

        $alreadyLinked = (int) ($item->shopee_item_id ?? 0) > 0;
        if ($alreadyLinked && ! $overwriteExisting) {
            return $row + [
                'status' => 'skipped',
                'message' => 'Sudah terhubung (centang overwrite untuk ganti).',
                'item' => $this->itemSnapshot($item),
            ];
        }

        $matchLabel = match ($matchedVia) {
            'legacy_code' => 'Match legacy_code → '.$item->code,
            'code' => 'Match code',
            'aria_item_id' => 'Match aria_item_id',
            default => 'Match',
        };

        if ($dryRun) {
            return $row + [
                'status' => 'ready',
                'message' => 'Siap — '.$matchLabel.'; Kode Variasi '.$shopeeModelId.'.',
                'item' => $this->itemSnapshot($item),
            ];
        }

        $models = $modelsByShopeeItem[$shopeeItemId] ?? null;
        $result = $this->applier->apply(
            $item,
            $shopeeItemId,
            $shopeeModelId > 0 ? $shopeeModelId : null,
            $models,
        );

        if (! ($result['ok'] ?? false)) {
            return $row + [
                'status' => 'error',
                'message' => (string) ($result['message'] ?? 'Gagal link.'),
                'item' => $this->itemSnapshot($item->fresh()),
            ];
        }

        return $row + [
            'status' => 'linked',
            'message' => $matchLabel.' — terhubung (model '.($result['shopee_model_id'] ?? $shopeeModelId).')',
            'item' => $this->itemSnapshot($item->fresh()),
        ];
    }

    /**
     * @param  array<string, Item>  $legacyMap
     * @param  array<string, Item>  $codeMap
     * @return array{item: Item, matched_via: string}|null
     */
    private function resolveItemBySku(string $sku, array $legacyMap, array $codeMap): ?array
    {
        $key = strtoupper(trim($sku));
        if ($key === '') {
            return null;
        }

        if (isset($legacyMap[$key])) {
            return ['item' => $legacyMap[$key], 'matched_via' => 'legacy_code'];
        }

        if (isset($codeMap[$key])) {
            return ['item' => $codeMap[$key], 'matched_via' => 'code'];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $parsedRows
     * @return array{legacy: array<string, Item>, code: array<string, Item>}
     */
    private function loadItemLookupMaps(array $parsedRows): array
    {
        $needles = [];
        foreach ($parsedRows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $needles[strtoupper($code)] = true;
            }
        }

        if ($needles === []) {
            return ['legacy' => [], 'code' => []];
        }

        $needleList = array_keys($needles);
        $items = Item::query()
            ->where(function ($q) use ($needleList) {
                $q->whereIn('code', $needleList)
                    ->orWhereIn('legacy_code', $needleList);
            })
            ->get();

        $legacyMap = [];
        $codeMap = [];
        foreach ($items as $item) {
            $legacy = strtoupper(trim((string) ($item->legacy_code ?? '')));
            if ($legacy !== '' && isset($needles[$legacy])) {
                $legacyMap[$legacy] = $item;
            }
            $code = strtoupper(trim((string) $item->code));
            if ($code !== '' && isset($needles[$code])) {
                $codeMap[$code] = $item;
            }
        }

        return ['legacy' => $legacyMap, 'code' => $codeMap];
    }

    /**
     * @param  list<array<string, mixed>>  $parsedRows
     * @return array<int, Item>
     */
    private function loadItemsById(array $parsedRows): array
    {
        $ids = collect($parsedRows)->pluck('aria_item_id')->filter(fn ($id) => (int) $id > 0)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return Item::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /**
     * @return array{id: int, code: string, shopee_item_id: ?int, shopee_model_id: ?int}
     */
    private function itemSnapshot(Item $item): array
    {
        return [
            'id' => (int) $item->id,
            'code' => (string) $item->code,
            'show_url' => $item->showUrl(),
            'shopee_item_id' => $item->shopee_item_id ? (int) $item->shopee_item_id : null,
            'shopee_model_id' => $item->shopee_model_id ? (int) $item->shopee_model_id : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{total: int, ready?: int, linked?: int, skipped: int, errors: int}
     */
    private function summarize(array $rows, bool $applied = false): array
    {
        $summary = [
            'total' => count($rows),
            'skipped' => 0,
            'errors' => 0,
        ];

        if ($applied) {
            $summary['linked'] = 0;
        } else {
            $summary['ready'] = 0;
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'ready') {
                $summary['ready'] = ($summary['ready'] ?? 0) + 1;
            } elseif ($status === 'linked') {
                $summary['linked'] = ($summary['linked'] ?? 0) + 1;
            } elseif ($status === 'skipped') {
                $summary['skipped']++;
            } elseif ($status === 'error') {
                $summary['errors']++;
            }
        }

        return $summary;
    }
}
