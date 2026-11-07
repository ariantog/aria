<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemCatalog;
use App\Support\ItemPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillItemGroupParentIdCommand extends Command
{
    protected $signature = 'items:backfill-group-parent-id
                            {--dry-run : Report changes without writing}
                            {--limit=0 : Max colorway groups to process (0 = all)}
                            {--copy-catalog : Copy empty parent catalog fields from item_group legacy columns}';

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
        $copyCatalog = (bool) $this->option('copy-catalog');
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
                if ($copyCatalog) {
                    $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
                }
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

            if ($copyCatalog) {
                $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
            }

            $linked++;
        }

        $this->info("Groups processed: {$groups->count()}; linked: {$linked}; parents created: {$parentsCreated}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    protected function copyLegacyGroupCatalogToParent(ItemGroup $group, ItemParent $parent, bool $dryRun): void
    {
        $attributes = [];

        if (trim((string) ($parent->description ?? '')) === '' && trim((string) ($group->description ?? '')) !== '') {
            $attributes['description'] = (string) $group->description;
        }

        if (trim((string) ($parent->description2 ?? '')) === '' && trim((string) ($group->description2 ?? '')) !== '') {
            $attributes['description2'] = (string) $group->description2;
        }

        if (ItemCatalog::parentColumnExists('url')
            && trim((string) ($parent->url ?? '')) === ''
            && trim((string) ($group->url ?? '')) !== '') {
            $attributes['url'] = (string) $group->url;
        }

        if (ItemCatalog::parentColumnExists('brand')
            && (int) ($parent->brand ?? 0) === 0
            && (int) ($group->brand ?? 0) > 0) {
            $attributes['brand'] = (int) ($group->brand ?? 0);
        }

        if (ItemCatalog::parentColumnExists('genre')
            && (int) ($parent->genre ?? 0) === 0
            && (int) ($group->genre ?? 0) > 0) {
            $attributes['genre'] = (int) ($group->genre ?? 0);
        }

        foreach (['price', 'reseller_price', 'cost', 'cost_cnh'] as $field) {
            if (! ItemCatalog::parentColumnExists($field)) {
                continue;
            }

            if ((float) ($parent->{$field} ?? 0) > 0) {
                continue;
            }

            if (ItemCatalog::groupColumnExists($field) && (float) ($group->{$field} ?? 0) > 0) {
                $attributes[$field] = (float) $group->{$field};
            }
        }

        if ($attributes === []) {
            return;
        }

        if ($dryRun) {
            $this->line("Would copy catalog from group #{$group->id} → parent #{$parent->id}");

            return;
        }

        $sample = $group->items()->whereNull('deleted_at')->orderBy('id')->first();
        if ($sample !== null) {
            ItemCatalog::applySharedCatalog($group, $sample, $attributes);
        }
    }
}
