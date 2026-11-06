<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemParentDisplayNameBackfill;
use App\Support\ItemProductTitle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Batch-apply parent catalog titles from item_parent_prices.product_name for item ids in Excel.
 *
 * Always rebuilds `items.name` as parent `product_name` + warna tag (manufactured) + size when
 * tags allow (`ItemProductTitle::buildDisplayNameWithBareTitle`), ignoring colorway `item_group.name`.
 *
 * When `items.alias` exists: set it to the parent title for every row so other reads resolve the
 * parent bare title instead of stale colorway names.
 *
 * @see doc/item-catalog-hierarchy.md (bare product title resolution)
 */
class BackfillItemAliasFromParentExcelCommand extends Command
{
    protected $signature = 'items:backfill-alias-from-parent-excel
                            {file : Path to .xlsx / .xls / .csv with item ids (first column)}
                            {--sheet= : Worksheet name (default: first sheet)}
                            {--column=A : Column letter for item id}
                            {--dry-run : Show planned changes without saving}';

    protected $description = 'Set items.name from parent product_name + color/size tags; optional items.alias = parent title when column exists';

    public function handle(ItemIdentityBuilder $identityBuilder): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('item_parent_prices') || ! Schema::hasColumn('item_parent_prices', 'product_name')) {
            $this->error('item_parent_prices.product_name is required for parent titles.');

            return self::FAILURE;
        }

        $column = strtoupper(trim((string) $this->option('column')));
        if ($column === '' || ! preg_match('/^[A-Z]+$/', $column)) {
            $this->error('Invalid --column (use Excel column letters, e.g. A).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            $this->error('Could not load spreadsheet: '.$e->getMessage());

            return self::FAILURE;
        }

        $sheetName = trim((string) $this->option('sheet'));
        $sheet = $sheetName !== ''
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if ($sheet === null) {
            $this->error("Worksheet not found: {$sheetName}");

            return self::FAILURE;
        }

        $ids = $this->collectIdsFromSheet($sheet, $column);
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $this->warn('No numeric item ids found in the file.');

            return self::SUCCESS;
        }

        $hasAliasColumn = Schema::hasColumn((new Item)->getTable(), 'alias');
        if (! $hasAliasColumn) {
            $this->warn('items.alias is missing — only items.name will be updated (no per-SKU bare-title column).');
        }

        $this->info('Found '.count($ids).' id(s). Processing...');

        $updated = 0;
        $skipped = 0;
        $missing = 0;
        $noParentTitle = 0;

        foreach ($ids as $id) {
            $item = Item::query()
                ->with(['group', 'tags'])
                ->find($id);

            if ($item === null) {
                $missing++;
                $this->line("  [missing] Item #{$id}");

                continue;
            }

            if (! $item->group_id || $item->group === null) {
                $skipped++;
                $this->line("  [skip] Item #{$id} — no item group");

                continue;
            }

            $parentKey = $identityBuilder->itemParentKey($item);
            $parentTitle = ItemProductTitle::parentProductNameForKey($parentKey);
            if ($parentTitle === '') {
                $noParentTitle++;
                $this->line("  [skip] Item #{$id} — no product_name for parent key {$parentKey}");

                continue;
            }

            $result = ItemParentDisplayNameBackfill::compute($item, $parentTitle, $hasAliasColumn);

            if (! $result['aliasChanged'] && ! $result['nameChanged']) {
                continue;
            }

            $updated++;
            if ($hasAliasColumn) {
                $action = "name ← parent + tags; alias ← {$parentTitle}";
            } else {
                $action = "name ← parent + tags ({$parentTitle}, no alias column)";
            }

            $this->line("  Item #{$id}: {$action}");
            if ($hasAliasColumn && $result['aliasChanged']) {
                $cur = $result['currentAlias'] === '' ? '(empty)' : $result['currentAlias'];
                $nxt = $result['normalizedDesiredAlias'] === '' ? '(empty)' : $result['normalizedDesiredAlias'];
                $this->line("    alias: {$cur} → {$nxt}");
            }
            if ($result['nameChanged']) {
                $this->line('    name: '.$item->name.' → '.$result['newName']);
            }

            if (! $dryRun) {
                $item->name = $result['newName'];
                $item->save();
            }
        }

        $this->newLine();
        $this->info('Rows in file: '.count($ids));
        $this->info("Would update / updated: {$updated}");
        if ($missing > 0) {
            $this->warn("Missing items: {$missing}");
        }
        if ($noParentTitle > 0) {
            $this->warn("Skipped (no parent product_name): {$noParentTitle}");
        }
        if ($skipped > 0) {
            $this->warn("Skipped (no group): {$skipped}");
        }
        if ($dryRun && $updated > 0) {
            $this->warn('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function collectIdsFromSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $columnLetter): array
    {
        $ids = [];
        $highestRow = (int) $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cell = $sheet->getCell($columnLetter.$row);
            $raw = $cell->getValue();
            if ($raw === null || $raw === '') {
                continue;
            }

            if (is_numeric($raw)) {
                $id = (int) $raw;
                if ($id > 0) {
                    $ids[] = $id;
                }

                continue;
            }

            if (is_string($raw) && ctype_digit(trim($raw))) {
                $ids[] = (int) trim($raw);
            }
        }

        return $ids;
    }
}
