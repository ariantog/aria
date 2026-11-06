<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemParentDisplayNameBackfill;
use App\Support\ItemParentTitleEligibility;
use App\Support\ItemProductTitle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * For each `item_group` id in a spreadsheet, resolve the parent key from a sample SKU, then rebuild
 * every SKU under that parent family (`ItemGroupHierarchyService::groupIdsForParentKey`) from
 * `item_parent_prices.product_name` when non-empty and contains no `-` or `/` (otherwise skip);
 * else `item_group.name` when non-empty and contains no `-` or `/`; else skip.
 */
class BackfillItemNamesFromParentByGroupExcelCommand extends Command
{
    protected $signature = 'items:backfill-names-from-parent-by-group-excel
                            {file : Path to .xlsx / .xls / .csv with item_group ids (first column)}
                            {--sheet= : Worksheet name (default: first sheet)}
                            {--column=A : Column letter for group id}
                            {--dry-run : Show planned changes without saving}';

    protected $description = 'Rebuild items.name from parent product_name + tags for every SKU under each listed group\'s parent family';

    public function handle(ItemIdentityBuilder $identityBuilder, ItemGroupHierarchyService $groupHierarchy): int
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

        $groupIds = $this->collectIdsFromSheet($sheet, $column);
        $groupIds = array_values(array_unique($groupIds));

        if ($groupIds === []) {
            $this->warn('No numeric group ids found in the file.');

            return self::SUCCESS;
        }

        $hasAliasColumn = Schema::hasColumn((new Item)->getTable(), 'alias');
        if (! $hasAliasColumn) {
            $this->warn('items.alias is missing — only items.name will be updated.');
        }

        $this->info('Found '.count($groupIds).' group id(s). Processing...');

        $itemsUpdated = 0;
        $missingGroup = 0;
        $emptyGroup = 0;
        $noParentTitle = 0;
        $syncedParentFromGroupName = 0;
        $skippedGroupNameLooksLikeCode = 0;
        $skippedParentProductNameLooksLikeCode = 0;

        foreach ($groupIds as $groupId) {
            $group = ItemGroup::query()->find($groupId);
            if ($group === null) {
                $missingGroup++;
                $this->line("  [missing] Group #{$groupId}");

                continue;
            }

            $sample = Item::query()
                ->with(['group', 'tags'])
                ->where('group_id', $groupId)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first();

            if ($sample === null) {
                $emptyGroup++;
                $this->line("  [skip] Group #{$groupId} — no items");

                continue;
            }

            $parentKey = $identityBuilder->itemParentKey($sample);
            ItemProductTitle::forgetParentProductNameCache($parentKey);
            $parentTitle = ItemProductTitle::parentProductNameForKey($parentKey);

            if ($parentTitle !== '' && ItemParentTitleEligibility::looksLikePcodeOrSegment($parentTitle)) {
                $skippedParentProductNameLooksLikeCode++;
                $this->line("  [skip] Group #{$groupId} — item_parent_prices.product_name looks like pcode (contains - or /): {$parentTitle}");

                continue;
            }

            if ($parentTitle === '') {
                $fallback = $this->marketingTitleFromGroupName((string) ($group->name ?? ''));
                if ($fallback === null) {
                    if (trim((string) ($group->name ?? '')) !== '' && ItemParentTitleEligibility::looksLikePcodeOrSegment((string) $group->name)) {
                        $skippedGroupNameLooksLikeCode++;
                        $this->line("  [skip] Group #{$groupId} — item_group.name looks like pcode (contains - or /)");
                    } else {
                        $noParentTitle++;
                        $this->line("  [skip] Group #{$groupId} — no product_name and no item_group.name for parent key {$parentKey}");
                    }

                    continue;
                }

                $parentTitle = $fallback;
                $this->line("  Group #{$groupId}: parent title from item_group.name → {$parentTitle} (key {$parentKey})");

                if (! $dryRun) {
                    ItemProductTitle::syncParentProductName($parentKey, $parentTitle);
                    $syncedParentFromGroupName++;
                }
            } else {
                $this->line("  Group #{$groupId}: parent {$parentTitle} (key {$parentKey})");
            }

            $familyGroupIds = $groupHierarchy->groupIdsForParentKey($parentKey);
            if ($familyGroupIds === []) {
                $familyGroupIds = [$groupId];
            }

            Item::query()
                ->with(['group', 'tags'])
                ->whereIn('group_id', $familyGroupIds)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(100, function ($items) use (
                    $dryRun,
                    $hasAliasColumn,
                    $parentTitle,
                    &$itemsUpdated,
                ): void {
                    foreach ($items as $item) {
                        $item->loadMissing(['group', 'tags']);
                        $result = ItemParentDisplayNameBackfill::compute($item, $parentTitle, $hasAliasColumn);

                        if (! $result['nameChanged'] && ! $result['aliasChanged']) {
                            continue;
                        }

                        $itemsUpdated++;
                        $this->line(
                            "    Item #{$item->id}: ".
                            ($hasAliasColumn
                                ? "name ← parent + tags; alias ← {$parentTitle}"
                                : "name ← parent + tags ({$parentTitle})")
                        );
                        if ($hasAliasColumn && $result['aliasChanged']) {
                            $cur = $result['currentAlias'] === '' ? '(empty)' : $result['currentAlias'];
                            $nxt = $result['normalizedDesiredAlias'] === '' ? '(empty)' : $result['normalizedDesiredAlias'];
                            $this->line("      alias: {$cur} → {$nxt}");
                        }
                        if ($result['nameChanged']) {
                            $this->line('      name: '.$item->name.' → '.$result['newName']);
                        }

                        if (! $dryRun) {
                            $item->name = $result['newName'];
                            $item->save();
                        }
                    }
                }, 'id');
        }

        $this->newLine();
        $this->info('Groups in file: '.count($groupIds));
        $this->info("Items would update / updated: {$itemsUpdated}");
        if ($syncedParentFromGroupName > 0) {
            $this->info("Parent rows synced from item_group.name: {$syncedParentFromGroupName}");
        }
        if ($skippedParentProductNameLooksLikeCode > 0) {
            $this->warn("Skipped groups (product_name contains - or /): {$skippedParentProductNameLooksLikeCode}");
        }
        if ($skippedGroupNameLooksLikeCode > 0) {
            $this->warn("Skipped groups (item_group.name contains - or /): {$skippedGroupNameLooksLikeCode}");
        }
        if ($missingGroup > 0) {
            $this->warn("Missing groups: {$missingGroup}");
        }
        if ($emptyGroup > 0) {
            $this->warn("Groups with no items: {$emptyGroup}");
        }
        if ($noParentTitle > 0) {
            $this->warn("Skipped groups (empty product_name and empty item_group.name): {$noParentTitle}");
        }
        if ($dryRun && $itemsUpdated > 0) {
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

    /**
     * Use `item_group.name` as a marketing title only when non-empty and not pcode-like (- or /).
     */
    private function marketingTitleFromGroupName(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (ItemParentTitleEligibility::looksLikePcodeOrSegment($raw)) {
            return null;
        }

        return strtoupper($raw);
    }
}
