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
     * @param  list<int>  $itemIds
     * @return array{
     *     applied: list<array{item_id: int, qty: int, sheet_id: int, sheet_name: string, sheet_url: string}>,
     *     skipped: list<array{item_id: int, reason: string}>
     * }
     */
    public function applyOneMonthRate(array $itemIds, Collection $monthlyRateByItemId, User $user): array
    {
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

            $monthly = (float) ($monthlyRateByItemId->get($itemId, 0.0));
            $qty = self::suggestedQtyFromMonthlyRate($monthly);
            if ($qty === 0) {
                $skipped[] = ['item_id' => $itemId, 'reason' => 'No net selling rate for the selected sales window.'];

                continue;
            }

            $result = $this->applyQtyToSheet($item, $qty, $user);
            if ($result['ok']) {
                $applied[] = [
                    'item_id' => $itemId,
                    'qty' => $qty,
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
     * @return array{ok: true, sheet_id: int, sheet_name: string, sheet_url: string}|array{ok: false, reason: string}
     */
    private function applyQtyToSheet(Item $item, int $qty, User $user): array
    {
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
        $this->sheetService->syncSkus($sheet);

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

        $targetQty = max((int) $cell->qty_restock, $qty);
        $this->cellService->saveQuantities($sheet, [
            ['id' => $cell->id, 'qty_restock' => $targetQty],
        ], $user);

        return [
            'ok' => true,
            'sheet_id' => $sheet->id,
            'sheet_name' => (string) $sheet->name,
            'sheet_url' => route('restock.sheets.show', $sheet),
        ];
    }
}
