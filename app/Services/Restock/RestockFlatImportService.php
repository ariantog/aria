<?php

namespace App\Services\Restock;

use App\Models\Item;
use App\Models\RestockCell;
use App\Models\RestockSheet;
use App\Models\User;
use InvalidArgumentException;

class RestockFlatImportService
{
    public function __construct(
        protected RestockMoveService $moveService,
        protected RestockGridBuilder $gridBuilder,
        protected RestockRecommendationApplyService $recommendationApply,
    ) {}

    /**
     * @param  list<array{line: int, sku: string, qty: int}>  $parsedRows
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     summary: array{moving: int, skipped: int, errors: int},
     *     can_apply: bool,
     *     direction_label: string,
     * }
     */
    public function preview(string $direction, array $parsedRows): array
    {
        if ($direction === RestockFlatPipeline::DIRECTION_SET_RESTOCK) {
            return $this->previewSetRestock($parsedRows);
        }

        $meta = RestockFlatPipeline::fromDirection($direction);
        $sourceField = $meta['source_field'];

        $lines = [];
        $seenSkus = [];
        /** @var array<string, int> $seenImportSlots line number of first use per sheet color/size row */
        $seenImportSlots = [];
        $moving = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($parsedRows as $row) {
            $sku = $row['sku'];
            $qty = (int) $row['qty'];
            $lineNo = (int) $row['line'];

            if ($qty === 0) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'skipped', 'Quantity is zero — no move.');
                $skipped++;

                continue;
            }

            if (isset($seenSkus[$sku])) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', 'Duplicate SKU in file (first on line '.$seenSkus[$sku].').');
                $errors++;

                continue;
            }
            $seenSkus[$sku] = $lineNo;

            $item = Item::findBySku($sku);
            if (! $item) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', 'SKU not found in items.');
                $errors++;

                continue;
            }

            $cells = RestockCell::query()
                ->where('item_id', $item->id)
                ->with('sheet:id,name')
                ->get();

            if ($cells->isEmpty()) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', 'SKU is not on any restock sheet.');
                $errors++;

                continue;
            }

            if ($cells->count() > 1) {
                $sheetNames = $cells->pluck('sheet.name')->filter()->unique()->implode(', ');
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', "SKU appears on multiple sheets: {$sheetNames}.");
                $errors++;

                continue;
            }

            /** @var RestockCell $cell */
            $cell = $cells->first();
            $displayCell = $this->gridBuilder->displayCellForCell($cell);
            $slotKey = $this->gridBuilder->importSlotKey($displayCell);

            if (isset($seenImportSlots[$slotKey])) {
                $lines[] = $this->line(
                    $lineNo,
                    $sku,
                    $qty,
                    'error',
                    'Duplicate color/size row on this sheet (first on line '.$seenImportSlots[$slotKey].'). Use one SKU per row — prefer the code from flat export.',
                );
                $errors++;

                continue;
            }
            $seenImportSlots[$slotKey] = $lineNo;

            $available = (int) $displayCell->{$sourceField};
            $sheetName = $displayCell->sheet?->name;
            $gridSku = strtoupper(trim((string) ($displayCell->item?->code ?? '')));
            $fileSku = strtoupper(trim($sku));

            if ($qty > $available) {
                $detail = "Quantity {$qty} exceeds available {$available} on sheet «{$sheetName}».";
                if ($gridSku !== '' && $gridSku !== $fileSku) {
                    $detail .= " File SKU {$sku} maps to catalog row «{$gridSku}» (what the sheet grid shows for this color).";
                } elseif ($displayCell->id !== $cell->id) {
                    $detail .= ' Another catalog SKU shares this color row; the grid shows the row with the highest pipeline qty.';
                }

                $lines[] = $this->line(
                    $lineNo,
                    $sku,
                    $qty,
                    'error',
                    $detail,
                    $displayCell->id,
                    $available,
                    $sheetName,
                );
                $errors++;

                continue;
            }

            $okMessage = "Move {$qty} on «{$sheetName}» (available {$available}";
            if ($gridSku !== '' && $gridSku !== $fileSku) {
                $okMessage .= ", sheet row SKU «{$gridSku}»";
            }
            $okMessage .= ').';

            $lines[] = $this->line(
                $lineNo,
                $sku,
                $qty,
                'ok',
                $okMessage,
                $displayCell->id,
                $available,
                $sheetName,
            );
            $moving += $qty;
        }

        return [
            'lines' => $lines,
            'summary' => [
                'moving' => $moving,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
            'can_apply' => $errors === 0 && $moving > 0,
            'has_ok_lines' => collect($lines)->contains(fn (array $line) => ($line['status'] ?? '') === 'ok'),
            'direction_label' => $meta['label'],
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, qty: int}>  $parsedRows
     * @param  list<int>|null  $includeLineNumbers  Preview line numbers to move; null = all OK lines (full apply).
     * @return array{moved: int, sheets: int, message: string}
     */
    public function apply(string $direction, array $parsedRows, User $user, ?array $includeLineNumbers = null): array
    {
        if ($direction === RestockFlatPipeline::DIRECTION_SET_RESTOCK) {
            return $this->applySetRestock($parsedRows, $user, $includeLineNumbers);
        }

        $preview = $this->preview($direction, $parsedRows);
        $linesToApply = $this->filterApplyLines($preview['lines'], $includeLineNumbers);

        if ($linesToApply === []) {
            throw new InvalidArgumentException('No lines selected to apply.');
        }

        if ($includeLineNumbers === null && ! $preview['can_apply']) {
            throw new InvalidArgumentException('Import cannot be applied — fix errors in preview first.');
        }

        $meta = RestockFlatPipeline::fromDirection($direction);
        $moveDirection = $meta['move'];

        /** @var array<int, list<array{id: int, qty: int}>> $bySheet */
        $bySheet = [];

        foreach ($linesToApply as $line) {
            $cellId = (int) $line['cell_id'];
            $cell = RestockCell::query()->find($cellId);
            if (! $cell) {
                continue;
            }

            $sheetId = (int) $cell->restock_sheet_id;
            $bySheet[$sheetId] ??= [];
            $bySheet[$sheetId][] = [
                'id' => $cellId,
                'qty' => (int) $line['qty'],
            ];
        }

        $totalMoved = 0;
        $sheetCount = 0;

        foreach ($bySheet as $sheetId => $cells) {
            $sheet = RestockSheet::query()->find($sheetId);
            if (! $sheet) {
                continue;
            }

            $totalMoved += $this->moveService->move(
                $sheet,
                $moveDirection,
                $cells,
                $user,
                \App\Support\RestockCellHistoryLabels::SOURCE_FLAT_IMPORT,
            );
            $sheetCount++;
        }

        return [
            'moved' => $totalMoved,
            'sheets' => $sheetCount,
            'message' => $totalMoved > 0
                ? "Moved {$totalMoved} unit(s) across {$sheetCount} sheet(s)."
                : 'Nothing to move.',
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, qty: int}>  $parsedRows
     * @return array{
     *     lines: list<array<string, mixed>>,
     *     summary: array{moving: int, skipped: int, errors: int},
     *     can_apply: bool,
     *     direction_label: string,
     * }
     */
    private function previewSetRestock(array $parsedRows): array
    {
        $meta = RestockFlatPipeline::fromDirection(RestockFlatPipeline::DIRECTION_SET_RESTOCK);
        $lines = [];
        $seenSkus = [];
        $setting = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($parsedRows as $row) {
            $sku = $row['sku'];
            $qty = (int) $row['qty'];
            $lineNo = (int) $row['line'];

            if ($qty === 0) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'skipped', 'Quantity is zero — no change.');
                $skipped++;

                continue;
            }

            if (isset($seenSkus[$sku])) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', 'Duplicate SKU in file (first on line '.$seenSkus[$sku].').');
                $errors++;

                continue;
            }
            $seenSkus[$sku] = $lineNo;

            $item = Item::findBySku($sku);
            if (! $item) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', 'SKU not found in items.');
                $errors++;

                continue;
            }

            $target = $this->recommendationApply->resolveRestockTarget($item, syncCatalog: false);
            if ($target['ok'] !== true) {
                $lines[] = $this->line($lineNo, $sku, $qty, 'error', $target['reason']);
                $errors++;

                continue;
            }

            $current = (int) $target['current_qty'];
            $sheetName = (string) $target['sheet_name'];

            if ($current === $qty) {
                $lines[] = $this->line(
                    $lineNo,
                    $sku,
                    $qty,
                    'skipped',
                    "Restock already {$qty} on «{$sheetName}».",
                    $target['cell_id'],
                    $current,
                    $sheetName,
                    $target['item_id'],
                );
                $skipped++;

                continue;
            }

            $lines[] = $this->line(
                $lineNo,
                $sku,
                $qty,
                'ok',
                "Set restock to {$qty} on «{$sheetName}» (current {$current}).",
                $target['cell_id'],
                $current,
                $sheetName,
                $target['item_id'],
            );
            $setting += $qty;
        }

        return [
            'lines' => $lines,
            'summary' => [
                'moving' => $setting,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
            'can_apply' => $errors === 0 && $setting > 0,
            'has_ok_lines' => collect($lines)->contains(fn (array $line) => ($line['status'] ?? '') === 'ok'),
            'direction_label' => $meta['label'],
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, qty: int}>  $parsedRows
     * @param  list<int>|null  $includeLineNumbers
     * @return array{moved: int, sheets: int, message: string}
     */
    private function applySetRestock(array $parsedRows, User $user, ?array $includeLineNumbers = null): array
    {
        $preview = $this->previewSetRestock($parsedRows);
        $linesToApply = $this->filterApplyLines($preview['lines'], $includeLineNumbers);

        if ($linesToApply === []) {
            throw new InvalidArgumentException('No lines selected to apply.');
        }

        if ($includeLineNumbers === null && ! $preview['can_apply']) {
            throw new InvalidArgumentException('Import cannot be applied — fix errors in preview first.');
        }

        $rows = [];
        foreach ($linesToApply as $line) {
            $itemId = (int) ($line['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $rows[] = [
                'item_id' => $itemId,
                'qty_restock' => (int) $line['qty'],
            ];
        }

        if ($rows === []) {
            throw new InvalidArgumentException('No lines selected to apply.');
        }

        $result = $this->recommendationApply->saveRestockQuantities(
            $rows,
            $user,
            \App\Support\RestockCellHistoryLabels::SOURCE_FLAT_IMPORT,
        );

        $sheetIds = collect($result['applied'])->pluck('sheet_id')->unique();
        $updated = count($result['applied']);
        $skipped = count($result['skipped']);

        if ($updated === 0) {
            $reason = $result['skipped'][0]['reason'] ?? 'Nothing to update.';

            throw new InvalidArgumentException($reason);
        }

        $message = "Updated restock qty for {$updated} SKU(s) across ".$sheetIds->count().' sheet(s).';
        if ($skipped > 0) {
            $message .= " {$skipped} line(s) skipped.";
        }

        return [
            'moved' => collect($result['applied'])->sum('qty'),
            'sheets' => $sheetIds->count(),
            'message' => $message,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<int>|null  $includeLineNumbers
     * @return list<array<string, mixed>>
     */
    private function filterApplyLines(array $lines, ?array $includeLineNumbers): array
    {
        if ($includeLineNumbers === null) {
            return array_values(array_filter(
                $lines,
                fn (array $line) => ($line['status'] ?? '') === 'ok',
            ));
        }

        $include = array_flip(array_map('intval', $includeLineNumbers));

        return array_values(array_filter(
            $lines,
            fn (array $line) => ($line['status'] ?? '') === 'ok'
                && isset($include[(int) ($line['line'] ?? 0)]),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        int $lineNo,
        string $sku,
        int $qty,
        string $status,
        string $message,
        ?int $cellId = null,
        ?int $available = null,
        ?string $sheetName = null,
        ?int $itemId = null,
    ): array {
        return [
            'line' => $lineNo,
            'sku' => $sku,
            'qty' => $qty,
            'status' => $status,
            'message' => $message,
            'cell_id' => $cellId,
            'available' => $available,
            'sheet_name' => $sheetName,
            'item_id' => $itemId,
        ];
    }
}
