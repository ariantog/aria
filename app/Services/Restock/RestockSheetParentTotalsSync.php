<?php

namespace App\Services\Restock;

use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\RestockSheetParentTotal;
use App\Services\Items\ItemIdentityBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestockSheetParentTotalsSync
{
    public function __construct(
        protected RestockGridBuilder $gridBuilder,
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    public function syncSheet(RestockSheet $sheet): void
    {
        if (! $this->tableExists()) {
            return;
        }

        $sheet->loadMissing([
            'cells.color',
            'cells.size',
            'cells.item.group',
            'cells.item.tags',
        ]);

        $pipelineByParent = $this->gridBuilder->pipelineTotalsByParentPcode($sheet);

        $cellsByParent = $sheet->cells
            ->filter(fn (RestockCell $cell) => $cell->item !== null)
            ->groupBy(fn (RestockCell $cell) => $this->identityBuilder->assetLancarParentPcode($cell->item));

        /** @var Collection<int, string> $parentPcodes */
        $parentPcodes = $cellsByParent->keys()
            ->merge(array_keys($pipelineByParent))
            ->unique()
            ->values();

        $rows = $parentPcodes->map(function (string $parentPcode) use ($sheet, $cellsByParent, $pipelineByParent) {
            /** @var Collection<int, RestockCell> $cells */
            $cells = $cellsByParent->get($parentPcode, collect());
            $pipeline = $pipelineByParent[$parentPcode] ?? [
                'restock' => 0,
                'production' => 0,
                'shipped' => 0,
            ];

            return [
                'restock_sheet_id' => $sheet->id,
                'parent_pcode' => $parentPcode,
                'qty_restock' => (int) $pipeline['restock'],
                'qty_production' => (int) $pipeline['production'],
                'qty_shipped' => (int) $pipeline['shipped'],
                'qty_missing' => (int) $cells->sum('qty_missing'),
                'urgent_count' => (int) $cells->where('is_urgent', true)->count(),
            ];
        })->all();

        DB::transaction(function () use ($sheet, $rows) {
            $keepPcodes = collect($rows)->pluck('parent_pcode')->all();

            RestockSheetParentTotal::query()
                ->where('restock_sheet_id', $sheet->id)
                ->when($keepPcodes !== [], fn ($q) => $q->whereNotIn('parent_pcode', $keepPcodes))
                ->when($keepPcodes === [], fn ($q) => $q)
                ->delete();

            foreach ($rows as $row) {
                RestockSheetParentTotal::query()->updateOrCreate(
                    [
                        'restock_sheet_id' => $row['restock_sheet_id'],
                        'parent_pcode' => $row['parent_pcode'],
                    ],
                    $row,
                );
            }
        });
    }

    /**
     * @param  iterable<int, RestockSheet|int>  $sheets
     */
    public function syncSheets(iterable $sheets): void
    {
        foreach ($sheets as $sheet) {
            if (is_int($sheet)) {
                $sheet = RestockSheet::query()->find($sheet);
            }

            if ($sheet instanceof RestockSheet) {
                $this->syncSheet($sheet);
            }
        }
    }

    protected function tableExists(): bool
    {
        return RestockSheetParentTotal::query()->getConnection()->getSchemaBuilder()->hasTable('restock_sheet_parent_totals');
    }
}
