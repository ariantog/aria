<?php

namespace App\Services\Restock;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;

class RestockRecommendationApplyService
{
    /** Target weeks of cover when deriving recommended restock qty (matches Jubelio-style exports). */
    public const COVER_WEEKS = 8;

    public function __construct(
        private readonly RestockSheetService $sheetService,
        private readonly RestockCellService $cellService,
    ) {}

    public static function suggestedQtyFromMonthlyRate(float $monthlyNet): int
    {
        if ($monthlyNet <= 0) {
            return 0;
        }

        return max(1, (int) ceil($monthlyNet));
    }

    /**
     * Units to order so on-hand stock reaches {@see COVER_WEEKS} weeks of cover at the period sell rate.
     */
    public static function suggestedQtyEightWeekCover(float $netPeriod, int $periodDays, float $stockQty): int
    {
        if ($netPeriod <= 0) {
            return 0;
        }

        $periodDays = max(1, $periodDays);
        $weeklyNet = ($netPeriod / $periodDays) * 7;
        $needed = ($weeklyNet * self::COVER_WEEKS) - max(0.0, $stockQty);

        if ($needed <= 0) {
            return 0;
        }

        return max(1, (int) round($needed));
    }

    /**
     * @param  list<int>  $itemIds
     * @return array{
     *     applied: list<array{item_id: int, qty: int, sheet_id: int, sheet_name: string, sheet_url: string}>,
     *     skipped: list<array{item_id: int, reason: string}>
     * }
     */
    /**
     * @param  Collection<int, int>|array<int, int>  $recommendedQtyByItemId
     */
    public function applyRecommendedQuantities(array $itemIds, Collection|array $recommendedQtyByItemId, User $user): array
    {
        $qtyMap = $recommendedQtyByItemId instanceof Collection
            ? $recommendedQtyByItemId
            : collect($recommendedQtyByItemId);

        $itemIds = array_values(array_unique(array_filter($itemIds, fn (int $id) => $id > 0)));
        if ($itemIds === []) {
            return ['applied' => [], 'skipped' => []];
        }

        $items = Item::query()
            ->with(['tags'])
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        $applied = [];
        $skipped = [];

        foreach ($itemIds as $itemId) {
            $item = $items->get($itemId);
            if ($item === null) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'SKU not found.'];

                continue;
            }

            $qty = max(0, (int) $qtyMap->get($itemId, 0));
            if ($qty === 0) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'No recommended restock qty for the selected sales window.'];

                continue;
            }

            $result = $this->setQtyOnTypeSheet(
                $item,
                $qty,
                $user,
                replace: true,
                source: \App\Support\RestockCellHistoryLabels::SOURCE_RECOMMENDATIONS_APPLY,
            );
            if ($result['ok']) {
                $applied[] = [
                    'item_id' => $itemId,
                    'qty' => $result['qty_saved'],
                    'sheet_id' => $result['sheet_id'],
                    'sheet_name' => $result['sheet_name'],
                    'sheet_url' => $result['sheet_url'],
                ];
            } else {
                $skipped[] = ['item_id' => $itemId, 'reason' => $result['reason']];
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @param  list<array{item_id: int, qty_restock: int}>  $rows
     * @return array{
     *     applied: list<array{item_id: int, qty: int, sheet_id: int, sheet_name: string, sheet_url: string}>,
     *     skipped: list<array{item_id: int, reason: string}>
     * }
     */
    public function saveRestockQuantities(array $rows, User $user): array
    {
        $applied = [];
        $skipped = [];

        $itemIds = array_values(array_unique(array_map(
            fn (array $row) => (int) ($row['item_id'] ?? 0),
            $rows,
        )));
        $itemIds = array_values(array_filter($itemIds, fn (int $id) => $id > 0));

        if ($itemIds === []) {
            return ['applied' => [], 'skipped' => []];
        }

        $items = Item::query()
            ->with(['tags'])
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $item = $items->get($itemId);
            if ($item === null) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'SKU not found.'];

                continue;
            }

            $qty = max(0, (int) ($row['qty_restock'] ?? 0));
            $result = $this->setQtyOnTypeSheet(
                $item,
                $qty,
                $user,
                replace: true,
                source: \App\Support\RestockCellHistoryLabels::SOURCE_RECOMMENDATIONS_SAVE,
            );
            if ($result['ok']) {
                $applied[] = [
                    'item_id' => $itemId,
                    'qty' => $result['qty_saved'],
                    'sheet_id' => $result['sheet_id'],
                    'sheet_name' => $result['sheet_name'],
                    'sheet_url' => $result['sheet_url'],
                ];
            } else {
                $skipped[] = ['item_id' => $itemId, 'reason' => $result['reason']];
            }
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * @return array{ok: true, qty_saved: int, sheet_id: int, sheet_name: string, sheet_url: string}|array{ok: false, reason: string}
     */
    private function setQtyOnTypeSheet(
        Item $item,
        int $qty,
        User $user,
        bool $replace,
        string $source,
    ): array {
        if (ItemType::coerce($item->type) !== ItemType::ASSET_LANCAR) {
            return ['ok' => false, 'reason' => 'Only asset lancar SKUs use restock sheets.'];
        }

        $typeTag = $item->tags->first(fn (Tag $tag) => (int) $tag->type === Tag::TYPE_TYPE);
        if ($typeTag === null) {
            return ['ok' => false, 'reason' => 'SKU has no TYPE tag.'];
        }

        $sheet = RestockSheet::query()->where('type_tag_id', $typeTag->id)->first();
        if ($sheet === null) {
            return ['ok' => false, 'reason' => "No restock sheet for TYPE {$typeTag->name}."];
        }

        $sheet->loadMissing('typeTag');
        $this->sheetService->syncSkus($sheet, $user);

        $inCatalog = $this->sheetService->itemBelongsToTypeCatalog($sheet->typeTag, $item->id);

        if (! $inCatalog) {
            return ['ok' => false, 'reason' => 'SKU is not in the restock sheet catalog for this TYPE.'];
        }

        $cell = RestockCell::query()
            ->where('restock_sheet_id', $sheet->id)
            ->where('item_id', $item->id)
            ->first();

        if ($cell === null) {
            return ['ok' => false, 'reason' => 'Could not create a restock cell for this SKU.'];
        }

        $targetQty = $replace ? $qty : max((int) $cell->qty_restock, $qty);
        $this->cellService->saveQuantities($sheet, [
            ['id' => $cell->id, 'qty_restock' => $targetQty],
        ], $user, $source);

        return [
            'ok' => true,
            'qty_saved' => $targetQty,
            'sheet_id' => $sheet->id,
            'sheet_name' => (string) $sheet->name,
            'sheet_url' => route('restock.sheets.show', $sheet),
        ];
    }
}
