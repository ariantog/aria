<?php

namespace App\Services\TikTokShop;

use App\Models\Item;
use App\Models\TikTokBulkLinkRun;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TikTokShopItemBulkLinkService
{
    private const SESSION_KEY = 'tiktok_bulk_link_preview';

    public const PREVIEW_DISPLAY_LIMIT = 150;

    public const BATCH_SIZE = 1000;

    public const BATCH_INTERVAL_SECONDS = 60;

    public const FAILED_RESULTS_LIMIT = 5000;

    public function __construct(
        private TikTokShopItemBulkLinkParser $parser,
        private TikTokShopItemLinkApplier $applier,
    ) {}

    public function activeRun(): ?TikTokBulkLinkRun
    {
        return TikTokBulkLinkRun::query()
            ->where('status', TikTokBulkLinkRun::STATUS_RUNNING)
            ->orderByDesc('id')
            ->first();
    }

    public function displayRun(): ?TikTokBulkLinkRun
    {
        $running = $this->activeRun();
        if ($running !== null) {
            return $running;
        }

        return TikTokBulkLinkRun::query()
            ->where('status', '!=', TikTokBulkLinkRun::STATUS_CANCELLED)
            ->orderByDesc('id')
            ->first();
    }

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
        $display = [
            'token' => $token,
            'rows' => array_slice($previewRows, 0, self::PREVIEW_DISPLAY_LIMIT),
            'rows_total' => count($previewRows),
            'rows_truncated' => count($previewRows) > self::PREVIEW_DISPLAY_LIMIT,
            'summary' => $this->summarize($previewRows),
        ];

        Cache::put(self::SESSION_KEY.':'.$token, [
            'filename' => $file->getClientOriginalName(),
            'rows' => $parsed,
            'display' => $display,
        ], now()->addHours(2));

        return $display;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function previewForToken(string $token): ?array
    {
        if ($token === '' || strlen($token) !== 32) {
            return null;
        }

        $payload = Cache::get(self::SESSION_KEY.':'.$token);
        if (! is_array($payload) || ! is_array($payload['display'] ?? null)) {
            return null;
        }

        return $payload['display'];
    }

    public function forgetPreviewToken(string $token): void
    {
        if ($token !== '' && strlen($token) === 32) {
            Cache::forget(self::SESSION_KEY.':'.$token);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, Item>
     */
    public function loadItemsForResultRows(array $rows): Collection
    {
        $ids = collect($rows)
            ->map(fn (array $row) => (int) (($row['item']['id'] ?? 0)))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Item::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    public function startApply(string $token, bool $overwriteExisting = false, ?int $userId = null): TikTokBulkLinkRun
    {
        if ($this->activeRun() !== null) {
            throw new \InvalidArgumentException('Bulk link masih berjalan — tunggu selesai atau batalkan dari halaman ini.');
        }

        $payload = Cache::pull(self::SESSION_KEY.':'.$token);
        if (! is_array($payload) || ! is_array($payload['rows'] ?? null)) {
            throw new \InvalidArgumentException('Preview kedaluwarsa — upload ulang file.');
        }

        $rows = $payload['rows'];
        $run = TikTokBulkLinkRun::query()->create([
            'user_id' => (int) ($userId ?? 0),
            'filename' => (string) ($payload['filename'] ?? ''),
            'overwrite_existing' => $overwriteExisting,
            'status' => TikTokBulkLinkRun::STATUS_RUNNING,
            'total_rows' => count($rows),
            'processed_rows' => 0,
            'linked_count' => 0,
            'skipped_count' => 0,
            'error_count' => 0,
            'payload' => $rows,
            'recent_results' => [],
            'failed_results' => [],
        ]);

        $this->processRun($run, ignoreInterval: true);

        return $run->fresh() ?? $run;
    }

    public function processDueRuns(): int
    {
        $runs = TikTokBulkLinkRun::query()
            ->where('status', TikTokBulkLinkRun::STATUS_RUNNING)
            ->orderBy('id')
            ->get();

        $batches = 0;
        foreach ($runs as $run) {
            if ($this->processRun($run)) {
                $batches++;
            }
        }

        return $batches;
    }

    public function shouldProcessNextBatch(TikTokBulkLinkRun $run): bool
    {
        if (! $run->isRunning()) {
            return false;
        }

        if ($run->processed_rows >= $run->total_rows) {
            return false;
        }

        if ($run->last_batch_at === null) {
            return true;
        }

        return $run->last_batch_at->lte(now()->subSeconds(self::BATCH_INTERVAL_SECONDS));
    }

    public function processRun(TikTokBulkLinkRun $run, bool $ignoreInterval = false): bool
    {
        if (! $ignoreInterval && ! $this->shouldProcessNextBatch($run)) {
            return false;
        }

        if ($run->processed_rows >= $run->total_rows) {
            $this->markCompleted($run);

            return false;
        }

        $allRows = $run->payload;
        if (! is_array($allRows)) {
            $this->markFailed($run, 'Payload bulk link korup.');

            return false;
        }

        $batch = array_slice($allRows, $run->processed_rows, self::BATCH_SIZE);
        if ($batch === []) {
            $this->markCompleted($run);

            return false;
        }

        try {
            $resultRows = $this->evaluateRows($batch, dryRun: false, overwriteExisting: (bool) $run->overwrite_existing);
        } catch (\Throwable $e) {
            $this->markFailed($run, $e->getMessage());

            return false;
        }

        $batchSummary = $this->summarize($resultRows, applied: true);
        $failed = is_array($run->failed_results) ? $run->failed_results : [];
        $failed = $this->appendFailedResults($failed, $resultRows);

        $run->linked_count += (int) ($batchSummary['linked'] ?? 0);
        $run->skipped_count += (int) $batchSummary['skipped'];
        $run->error_count += (int) $batchSummary['errors'];
        $run->processed_rows += count($batch);
        $run->recent_results = [];
        $run->failed_results = $failed;
        $run->last_batch_at = now();

        if ($run->processed_rows >= $run->total_rows) {
            $run->status = TikTokBulkLinkRun::STATUS_COMPLETED;
            $run->completed_at = now();
        }

        $run->save();

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function findRun(int $id): ?TikTokBulkLinkRun
    {
        return TikTokBulkLinkRun::query()->find($id);
    }

    public function dismissErrorDisplay(TikTokBulkLinkRun $run): void
    {
        if ($run->errors_dismissed_at !== null) {
            return;
        }

        $run->errors_dismissed_at = now();
        $run->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function storedFailedResults(TikTokBulkLinkRun $run): array
    {
        $failed = $run->failed_results;

        return is_array($failed) ? $failed : [];
    }

    public function runForDisplay(TikTokBulkLinkRun $run): array
    {
        $storedFailed = $this->storedFailedResults($run);
        $errorsDismissed = $run->errors_dismissed_at !== null;
        $failed = $errorsDismissed ? [] : $storedFailed;

        return [
            'id' => $run->id,
            'filename' => $run->filename,
            'status' => $run->status,
            'total_rows' => $run->total_rows,
            'processed_rows' => $run->processed_rows,
            'linked_count' => $run->linked_count,
            'skipped_count' => $run->skipped_count,
            'error_count' => $run->error_count,
            'progress_percent' => $run->progressPercent(),
            'last_batch_at' => $run->last_batch_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'error_message' => $run->error_message,
            'batch_size' => self::BATCH_SIZE,
            'batch_interval_seconds' => self::BATCH_INTERVAL_SECONDS,
            'rows' => [],
            'rows_truncated' => false,
            'failed_rows' => $failed,
            'failed_rows_truncated' => $run->error_count > count($storedFailed),
            'errors_dismissed' => $errorsDismissed,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $batchRows
     * @return list<array<string, mixed>>
     */
    private function appendFailedResults(array $existing, array $batchRows): array
    {
        foreach ($batchRows as $row) {
            if (($row['status'] ?? '') === 'error') {
                $existing[] = $row;
            }
        }

        if (count($existing) > self::FAILED_RESULTS_LIMIT) {
            return array_slice($existing, -self::FAILED_RESULTS_LIMIT);
        }

        return $existing;
    }

    private function markCompleted(TikTokBulkLinkRun $run): void
    {
        if ($run->status === TikTokBulkLinkRun::STATUS_COMPLETED) {
            return;
        }

        $run->status = TikTokBulkLinkRun::STATUS_COMPLETED;
        $run->completed_at = now();
        $run->save();
    }

    private function markFailed(TikTokBulkLinkRun $run, string $message): void
    {
        $run->status = TikTokBulkLinkRun::STATUS_FAILED;
        $run->error_message = $message;
        $run->completed_at = now();
        $run->save();
    }

    /**
     * @param  list<array<string, mixed>>  $parsedRows
     * @return list<array<string, mixed>>
     */
    private function evaluateRows(array $parsedRows, bool $dryRun, bool $overwriteExisting = false): array
    {
        $lookup = $this->loadItemLookupMaps($parsedRows);
        $itemsById = $this->loadItemsById($parsedRows);

        $out = [];
        foreach ($parsedRows as $row) {
            $out[] = $this->evaluateRow(
                $row,
                $lookup['legacy'],
                $lookup['code'],
                $itemsById,
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
     * @return array<string, mixed>
     */
    private function evaluateRow(
        array $row,
        array $legacyMap,
        array $codeMap,
        array $itemsById,
        bool $dryRun,
        bool $overwriteExisting,
    ): array {
        $code = isset($row['code']) ? trim((string) $row['code']) : '';
        $ariaItemId = (int) ($row['aria_item_id'] ?? 0);
        $productId = trim((string) ($row['tiktok_product_id'] ?? ''));
        $skuId = trim((string) ($row['tiktok_sku_id'] ?? ''));

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
                    ? 'SKU tidak ditemukan (code lalu legacy_code).'
                    : 'SKU / seller_sku kosong.',
                'item' => null,
            ];
        }

        if ($skuId === '') {
            return $row + [
                'status' => 'error',
                'message' => 'sku_id TikTok kosong atau tidak valid.',
                'item' => $this->itemSnapshot($item),
            ];
        }

        if ($productId === '') {
            $existingProduct = trim((string) ($item->tiktok_shop_product_id ?? ''));
            if ($existingProduct !== '') {
                $productId = $existingProduct;
            } else {
                return $row + [
                    'status' => 'error',
                    'message' => 'product_id kosong — diperlukan untuk SKU yang belum pernah di-link.',
                    'item' => $this->itemSnapshot($item),
                ];
            }
        }

        if ($this->shouldSkipLinkRow($item, $productId, $skuId, $overwriteExisting)) {
            return $row + [
                'status' => 'skipped',
                'message' => $this->skipLinkMessage($item, $productId, $skuId, $overwriteExisting),
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
                'message' => 'Siap link — '.$matchLabel.'; SKU '.$skuId.'.',
                'item' => $this->itemSnapshot($item),
                'tiktok_product_id' => $productId,
                'tiktok_sku_id' => $skuId,
            ];
        }

        $result = $this->applier->apply($item, $productId, $skuId, null);

        if (! ($result['ok'] ?? false)) {
            return $row + [
                'status' => 'error',
                'message' => (string) ($result['message'] ?? 'Gagal link.'),
                'item' => $this->itemSnapshot($item->fresh()),
            ];
        }

        if ($result['unchanged'] ?? false) {
            return $row + [
                'status' => 'skipped',
                'message' => 'Sudah terhubung — ID sama.',
                'item' => $this->itemSnapshot($item->fresh()),
            ];
        }

        return $row + [
            'status' => 'linked',
            'message' => $matchLabel.' — terhubung (sku '.$skuId.')',
            'item' => $this->itemSnapshot($item->fresh()),
            'tiktok_product_id' => $productId,
            'tiktok_sku_id' => $skuId,
        ];
    }

    private function shouldSkipLinkRow(Item $item, string $productId, string $skuId, bool $overwriteExisting): bool
    {
        $existingProduct = trim((string) ($item->tiktok_shop_product_id ?? ''));
        if ($existingProduct === '') {
            return false;
        }

        if (! $overwriteExisting) {
            return true;
        }

        $existingSku = trim((string) ($item->tiktok_shop_sku_id ?? ''));

        return $existingProduct === $productId && $existingSku === $skuId;
    }

    private function skipLinkMessage(Item $item, string $productId, string $skuId, bool $overwriteExisting): string
    {
        if (! $overwriteExisting) {
            return 'Sudah terhubung — lewati (centang overwrite untuk ganti).';
        }

        $existingProduct = trim((string) ($item->tiktok_shop_product_id ?? ''));
        $existingSku = trim((string) ($item->tiktok_shop_sku_id ?? ''));

        if ($existingProduct === $productId && $existingSku === $skuId) {
            return 'Sudah terhubung — ID sama.';
        }

        return 'Sudah terhubung — lewati.';
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

        if (isset($codeMap[$key])) {
            return ['item' => $codeMap[$key], 'matched_via' => 'code'];
        }

        if (isset($legacyMap[$key])) {
            return ['item' => $legacyMap[$key], 'matched_via' => 'legacy_code'];
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
     * @return array{id: int, code: string, tiktok_shop_product_id: ?string, tiktok_shop_sku_id: ?string}
     */
    private function itemSnapshot(Item $item): array
    {
        return [
            'id' => (int) $item->id,
            'code' => (string) $item->code,
            'tiktok_shop_product_id' => filled($item->tiktok_shop_product_id) ? (string) $item->tiktok_shop_product_id : null,
            'tiktok_shop_sku_id' => filled($item->tiktok_shop_sku_id) ? (string) $item->tiktok_shop_sku_id : null,
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