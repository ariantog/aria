<?php

namespace App\Console\Commands;

use App\Enums\ItemBrand;
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

    protected $description = 'Link item_group.parent_id to item_parents; optionally copy legacy group catalog onto parent rows.';

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
        $catalogCopied = 0;

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
                    $catalogCopied += $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
                }
                $parentsCreated++;
                $linked++;

                continue;
            }

            if ((int) $group->parent_id === (int) $parent->id) {
                if ($copyCatalog) {
                    $catalogCopied += $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
                }

                continue;
            }

            if ($dryRun) {
                $this->line("Would set group #{$group->id} → parent #{$parent->id}");
                if ($copyCatalog) {
                    $catalogCopied += $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
                }
                $linked++;

                continue;
            }

            $group->parent_id = (int) $parent->id;
            $group->save();

            if ($copyCatalog) {
                $catalogCopied += $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
            }

            $linked++;
        }

        if ($copyCatalog) {
            $alreadyLinkedQuery = ItemGroup::query()
                ->where('parent_id', '>', 0)
                ->whereHas('items', fn ($q) => $q->whereNull('deleted_at'))
                ->orderBy('id');

            if ($limit > 0) {
                $alreadyLinkedQuery->limit($limit);
            }

            $idsNeedingLink = $groups->pluck('id')->all();

            foreach ($alreadyLinkedQuery->get() as $group) {
                if (in_array($group->id, $idsNeedingLink, true)) {
                    continue;
                }

                $parent = ItemParent::query()->find((int) $group->parent_id);
                if ($parent === null) {
                    continue;
                }

                $catalogCopied += $this->copyLegacyGroupCatalogToParent($group, $parent, $dryRun);
            }
        }

        $alreadyLinked = ItemGroup::query()
            ->where('parent_id', '>', 0)
            ->whereHas('items', fn ($q) => $q->whereNull('deleted_at'))
            ->count();

        $this->info('Groups needing parent_id link: '.$groups->count()
            .'; linked this run: '.$linked
            .'; parents created: '.$parentsCreated
            .($copyCatalog
                ? '; catalog copy updates: '.$catalogCopied.($dryRun ? ' (dry-run)' : '')
                : '')
            .($dryRun ? ' (dry-run)' : ''));
        $this->line("Colorway groups already on parent_id (>0): {$alreadyLinked}");

        if ($groups->count() === 0 && $linked === 0 && (! $copyCatalog || $catalogCopied === 0)) {
            $this->comment(
                $copyCatalog
                    ? 'Nothing to link (parent_id already set). If legacy catalog still lives on item_group, re-run with --copy-catalog after migration 2026_11_07_120000 (url/brand/genre on item_parents).'
                    : 'Nothing to link — every colorway with items already has parent_id. Use --copy-catalog to lift legacy group catalog onto item_parents.'
            );
        }

        return self::SUCCESS;
    }

    protected function copyLegacyGroupCatalogToParent(ItemGroup $group, ItemParent $parent, bool $dryRun): int
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

        $parentBrand = $this->catalogBrandId($parent->brand);
        $groupBrand = $this->catalogBrandId($group->brand);

        if (ItemCatalog::parentColumnExists('brand')
            && $parentBrand === 0
            && $groupBrand > 0) {
            $attributes['brand'] = $groupBrand;
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
            return 0;
        }

        if ($dryRun) {
            $this->line("Would copy catalog from group #{$group->id} → parent #{$parent->id} (".implode(', ', array_keys($attributes)).')');

            return 1;
        }

        $sample = $group->items()->whereNull('deleted_at')->orderBy('id')->first();
        if ($sample !== null) {
            ItemCatalog::applySharedCatalog($group, $sample, $attributes);

            return 1;
        }

        return 0;
    }

    protected function catalogBrandId(mixed $brand): int
    {
        if ($brand instanceof ItemBrand) {
            return $brand->value;
        }

        return (int) ($brand ?? 0);
    }
}
