<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillItemGroupParentIdCommand extends Command
{
    protected $signature = 'items:backfill-group-parent-id
                            {--dry-run : Report changes without writing}
                            {--limit=0 : Max colorway groups to process (0 = all)}';

    protected $description = 'Link each item_group row to item_parents via parent_id (run once on production after migration).';

    public function handle(ItemIdentityBuilder $identityBuilder): int
    {
        if (! Schema::hasColumn('item_group', 'parent_id')) {
            $this->error('item_group.parent_id is missing. Run migrations first.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('item_parents')) {
            $this->error('item_parents table is missing. Run migrations first.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $query = ItemGroup::query()
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->whereHas('items', fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $groups = $query->get();
        $linked = 0;
        $parentsCreated = 0;

        foreach ($groups as $group) {
            $sample = Item::query()
                ->where('group_id', $group->id)
                ->whereNull('deleted_at')
                ->with(['tags', 'group'])
                ->orderBy('id')
                ->first();

            if ($sample === null) {
                continue;
            }

            $parentKey = $identityBuilder->itemParentKey($sample);
            $parent = ItemParent::query()->where('parent_key', $parentKey)->first();

            if ($parent === null) {
                if ($dryRun) {
                    $this->line("Would create item_parents for key {$parentKey} (group #{$group->id})");
                    $parentsCreated++;
                    $linked++;

                    continue;
                }

                $parent = ItemPricing::ensureParentForItem($sample);
                $parentsCreated++;
                $linked++;

                continue;
            }

            if ((int) $group->parent_id === (int) $parent->id) {
                continue;
            }

            if ($dryRun) {
                $this->line("Would set group #{$group->id} → parent #{$parent->id}");
                $linked++;

                continue;
            }

            $group->parent_id = (int) $parent->id;
            $group->save();
            $linked++;
        }

        $this->info("Groups processed: {$groups->count()}; linked: {$linked}; parents created: {$parentsCreated}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
