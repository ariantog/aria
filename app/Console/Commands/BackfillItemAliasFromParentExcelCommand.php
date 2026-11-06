<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Tag;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemProductTitle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Batch-set SKU bare titles (items.alias) from parent catalog (item_parent_prices.product_name)
 * for a list of item ids in an Excel file.
 *
 * When a SKU has no warna tag: alias = parent's product name; display name follows usual rules
 * (manufactured: parent title + warna from tag if any + size).
 * When warna is tagged: alias is cleared so display names use colorway/parent resolution ("like usual").
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

    protected $description = 'Set items.alias from parent product name for ids listed in a spreadsheet (no warna → copy parent title; warna tagged → clear alias, rebuild name)';

    public function handle(ItemIdentityBuilder $identityBuilder): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("File not readable: {$path}");

            return self::FAILURE;
        }

        if (! Schema::hasColumn('items', 'alias')) {
            $this->error('The items.alias column is missing; run migrations first.');

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

            $warnaTag = $item->tags->firstWhere('type', Tag::TYPE_WARNA);
            $desiredAlias = $warnaTag !== null ? '' : $parentTitle;

            $currentAlias = strtoupper(trim((string) ($item->alias ?? '')));
            $normalizedDesired = strtoupper(trim($desiredAlias));

            $item->alias = $desiredAlias;
            $newName = ItemProductTitle::buildDisplayName($item);

            $nameChanged = strtoupper(trim((string) $item->name)) !== strtoupper(trim($newName));
            $aliasChanged = $currentAlias !== $normalizedDesired;

            if (! $aliasChanged && ! $nameChanged) {
                continue;
            }

            $updated++;
            $action = $warnaTag !== null
                ? 'clear alias (warna tagged), rebuild name'
                : "alias ← parent title ({$parentTitle})";

            $this->line("  Item #{$id}: {$action}");
            if ($aliasChanged) {
                $this->line('    alias: '.($currentAlias === '' ? '(empty)' : $currentAlias).' → '.($normalizedDesired === '' ? '(empty)' : $normalizedDesired));
            }
            if ($nameChanged) {
                $this->line('    name: '.$item->name.' → '.$newName);
            }

            if (! $dryRun) {
                $item->name = $newName;
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
