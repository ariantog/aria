<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\ItemPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Temporary migration helper: copy colorway (item_group) pricing onto SKUs that still store 0.
 *
 * Does not change item_group rows or parent pricing. Safe to re-run (skips SKUs that already have amount &gt; 0).
 */
class BackfillSkuPricingFromColorway extends Command
{
    protected $signature = 'app:backfill-sku-pricing-from-colorway
                            {--dry-run : Report counts without writing}
                            {--chunk=500 : Items per batch}
                            {--group= : Limit to one item_group id}';

    protected $description = 'Copy item_group price/reseller/cost fields onto SKUs that still store 0 (temporary migration)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $groupId = $this->option('group');
        $groupFilter = $groupId !== null && $groupId !== '' ? (int) $groupId : null;

        $fields = array_values(array_filter(
            ItemPricing::FIELDS,
            fn (string $field) => Schema::hasColumn('items', $field) && Schema::hasColumn('item_group', $field),
        ));

        if ($fields === []) {
            $this->error('No pricing columns available on items / item_group.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fields: %s%s',
            implode(', ', $fields),
            $dryRun ? ' (dry run)' : '',
        ));

        $itemsTouched = 0;
        $fieldUpdates = array_fill_keys($fields, 0);

        $query = Item::query()
            ->where('group_id', '>', 0)
            ->with('group')
            ->orderBy('id');

        if ($groupFilter !== null) {
            $query->where('group_id', $groupFilter);
        }

        $query->chunkById($chunk, function ($items) use ($dryRun, $fields, &$itemsTouched, &$fieldUpdates): void {
            foreach ($items as $item) {
                $group = $item->group;
                if ($group === null) {
                    continue;
                }

                $patch = [];
                foreach ($fields as $field) {
                    $skuValue = (float) ($item->{$field} ?? 0);
                    $colorwayValue = (float) ($group->{$field} ?? 0);

                    if ($skuValue > 0 || $colorwayValue <= 0) {
                        continue;
                    }

                    $patch[$field] = $colorwayValue;
                    $fieldUpdates[$field]++;
                }

                if ($patch === []) {
                    continue;
                }

                $itemsTouched++;

                if ($dryRun) {
                    continue;
                }

                $item->forceFill($patch)->save();
            }
        });

        $this->table(
            ['Field', 'SKU rows filled'],
            collect($fieldUpdates)->map(fn ($count, $field) => [$field, $count])->values()->all(),
        );
        $this->info(sprintf(
            '%s %d SKU row(s) would receive at least one field.',
            $dryRun ? 'Dry run:' : 'Updated:',
            $itemsTouched,
        ));

        return self::SUCCESS;
    }
}
