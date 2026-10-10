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
        $meta = RestockFlatPipeline::fromDirection($direction);
        $sourceField = $meta['source_field'];

        $lines = [];
        $seenSkus = [];
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
            $available = (int) $cell->{$sourceField};

            if ($qty > $available) {
                $lines[] = $this->line(
                    $lineNo,
                    $sku,
                    $qty,
                    'error',
                    "Quantity {$qty} exceeds available {$available} on sheet «{$cell->sheet?->name}».",
                    $cell->id,
                    $available,
                    $cell->sheet?->name,
                );
                $errors++;

                continue;
            }

            $lines[] = $this->line(
                $lineNo,
                $sku,
                $qty,
                'ok',
                "Move {$qty} on «{$cell->sheet?->name}» (available {$available}).",
                $cell->id,
                $available,
                $cell->sheet?->name,
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
            'direction_label' => $meta['label'],
        ];
    }

    /**
     * @param  list<array{line: int, sku: string, qty: int}>  $parsedRows
     * @return array{moved: int, sheets: int, message: string}
     */
    public function apply(string $direction, array $parsedRows, User $user): array
    {
        $preview = $this->preview($direction, $parsedRows);
        if (! $preview['can_apply']) {
            throw new InvalidArgumentException('Import cannot be applied — fix errors in preview first.');
        }

        $meta = RestockFlatPipeline::fromDirection($direction);
        $moveDirection = $meta['move'];

        /** @var array<int, list<array{id: int, qty: int}>> $bySheet */
        $bySheet = [];

        foreach ($preview['lines'] as $line) {
            if (($line['status'] ?? '') !== 'ok') {
                continue;
            }

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

            $totalMoved += $this->moveService->move($sheet, $moveDirection, $cells, $user);
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
        ];
    }
}
