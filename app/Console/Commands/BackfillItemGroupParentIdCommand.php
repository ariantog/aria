<?php

namespace App\Console\Commands;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemParent;
use App\Services\Items\ItemGroupHierarchyService;
use App\Services\Items\ItemIdentityBuilder;
use App\Support\ItemCatalog;
use App\Support\ItemPricing;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BackfillItemGroupParentIdCommand extends Command
{
    protected $signature = 'items:backfill-group-parent-id
                            {--dry-run : Report changes without writing}
                            {--limit=0 : Max parents (copy-catalog) or colorway groups (link pass) to process (0 = all)}
                            {--parent-id= : With --copy-catalog, only this item_parents.id}
                            {--copy-catalog : Copy empty parent catalog fields from item_group legacy columns}';

    protected $description = 'Link item_group.parent_id to item_parents; optionally copy legacy group catalog onto parent rows.';

    public function handle(
        ItemIdentityBuilder $identityBuilder,
        ItemGroupHierarchyService $hierarchy,
    ): int {
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
            $sample = $this->sampleItemForGroup($group);

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

        if ($copyCatalog) {
            $catalogCopied = $this->copyCatalogForLinkedParents($dryRun, $limit, $hierarchy, $identityBuilder);
        }

        $alreadyLinked = ItemGroup::query()
            ->where('parent_id', '>', 0)
            ->whereHas('items', fn ($q) => $q->whereNull('deleted_at'))
            ->count();

        $parentsWithGroups = ItemParent::query()
            ->whereHas('colorwayGroups', fn ($q) => $q
                ->where('parent_id', '>', 0)
                ->whereHas('items', fn ($iq) => $iq->whereNull('deleted_at')))
            ->count();

        $this->info('Groups needing parent_id link: '.$groups->count()
            .'; linked this run: '.$linked
            .'; parents created: '.$parentsCreated
            .($copyCatalog
                ? '; catalog parents updated: '.$catalogCopied.($dryRun ? ' (dry-run)' : '')
                : '')
            .($dryRun ? ' (dry-run)' : ''));
        $this->line("Colorway groups already on parent_id (>0): {$alreadyLinked}");
        $this->line("item_parents with ≥1 linked colorway: {$parentsWithGroups}");

        if ($groups->count() === 0 && $linked === 0 && (! $copyCatalog || $catalogCopied === 0)) {
            $this->comment(
                $copyCatalog
                    ? 'No parent rows were updated. Confirm item_group still has legacy catalog and parent_id points at item_parents.id.'
                    : 'Nothing to link — every colorway with items already has parent_id. Use --copy-catalog to lift legacy group catalog onto item_parents.'
            );
        }

        return self::SUCCESS;
    }

    protected function copyCatalogForLinkedParents(
        bool $dryRun,
        int $limit,
        ItemGroupHierarchyService $hierarchy,
        ItemIdentityBuilder $identityBuilder,
    ): int {
        $onlyParentId = max(0, (int) $this->option('parent-id'));

        $parentQuery = ItemParent::query()
            ->whereHas('colorwayGroups')
            ->orderBy('id');

        if ($onlyParentId > 0) {
            $parentQuery->whereKey($onlyParentId);
        }

        if ($limit > 0) {
            $parentQuery->limit($limit);
        }

        $updates = 0;

        foreach ($parentQuery->get() as $parent) {
            $updates += $this->copyLegacyCatalogOntoParent($parent, $dryRun, $hierarchy, $identityBuilder);
        }

        return $updates;
    }

    protected function copyLegacyCatalogOntoParent(
        ItemParent $parent,
        bool $dryRun,
        ItemGroupHierarchyService $hierarchy,
        ItemIdentityBuilder $identityBuilder,
    ): int {
        $parent->refresh();

        $groups = ItemGroup::query()
            ->where('parent_id', (int) $parent->id)
            ->with(['items' => fn ($q) => $q->orderBy('id')])
            ->orderBy('id')
            ->get();

        if ($groups->isEmpty()) {
            $this->warn("Parent #{$parent->id} has no item_group rows with parent_id={$parent->id} — nothing to copy.");

            return 0;
        }

        $sample = $this->sampleItemPreferringActive($groups);
        $itemType = ItemType::ITEM;
        $parentKey = trim((string) ($parent->parent_key ?? ''));

        if ($sample !== null) {
            $sample->loadMissing(['group', 'tags']);
            $itemType = $sample->type instanceof ItemType
                ? $sample->type
                : ItemType::coerce($sample->type) ?? ItemType::ITEM;

            if ($parentKey === '') {
                $parentKey = $identityBuilder->itemParentKey($sample);
            }
        } elseif ($parentKey === '' && trim((string) $groups->first()?->master) !== '') {
            $parentKey = ItemType::ASSET_LANCAR->value.':'.strtoupper(trim((string) $groups->first()->master));
            $itemType = ItemType::ASSET_LANCAR;
        }

        $attributes = $this->buildLegacyCatalogAttributes(
            $parent,
            $groups,
            $parentKey,
            $itemType,
            $hierarchy,
        );

        if ($attributes === []) {
            $this->line(
                'Parent #'.$parent->id.' ('.($parent->parent_key ?? '').'): groups '
                .$groups->pluck('id')->join(', ')
                .' — parent already has catalog or groups have no legacy fields to lift.'
            );

            return 0;
        }

        $groupIds = $groups->pluck('id')->join(', ');

        if ($dryRun) {
            $this->line(
                'Would copy catalog → parent #'.$parent->id.' ('.($parent->parent_key ?? '').')'
                ." from colorway group id(s): {$groupIds}; fields: "
                .implode(', ', array_keys($attributes))
            );

            return 1;
        }

        if (ItemCatalog::applyToParentRecord($parent, $attributes)) {
            $this->line("Copied catalog → parent #{$parent->id} from group id(s): {$groupIds}");

            return 1;
        }

        return 0;
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function sampleItemPreferringActive(Collection $groups): ?Item
    {
        foreach ($groups as $group) {
            $active = $this->sampleItemForGroup($group);
            if ($active !== null) {
                return $active;
            }
        }

        foreach ($groups as $group) {
            $any = $group->items->first();
            if ($any !== null) {
                return $any;
            }
        }

        return Item::query()
            ->whereIn('group_id', $groups->pluck('id')->all())
            ->orderByRaw('CASE WHEN deleted_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     * @return array<string, mixed>
     */
    protected function buildLegacyCatalogAttributes(
        ItemParent $parent,
        Collection $groups,
        string $parentKey,
        ItemType $itemType,
        ItemGroupHierarchyService $hierarchy,
    ): array {
        $attributes = [];

        if (trim((string) ($parent->product_name ?? '')) === ''
            && ItemCatalog::parentColumnExists('product_name')) {
            $productName = trim($hierarchy->legacyParentProductName($groups, $parentKey, $itemType));
            if ($productName === '') {
                $productName = $this->firstNonEmptyFromGroups($groups, 'name');
            }
            if ($productName !== '') {
                $attributes['product_name'] = $productName;
            }
        }

        foreach (['description', 'description2'] as $field) {
            if (trim((string) ($parent->{$field} ?? '')) !== '') {
                continue;
            }

            $value = $this->firstNonEmptyFromGroups($groups, $field);
            if ($value === '') {
                $value = $this->firstNonEmptyFromItems($groups, $field);
            }

            if ($value !== '') {
                $attributes[$field] = $value;
            }
        }

        if (ItemCatalog::parentColumnExists('url') && trim((string) ($parent->url ?? '')) === '') {
            $url = $this->firstNonEmptyFromGroups($groups, 'url');
            if ($url !== '') {
                $attributes['url'] = $url;
            }
        }

        $parentBrand = $this->catalogBrandId($parent->brand);
        $groupBrand = $this->bestBrandFromGroups($groups);
        if (ItemCatalog::parentColumnExists('brand')
            && $parentBrand === 0
            && $groupBrand > 0) {
            $attributes['brand'] = $groupBrand;
        }

        if (ItemCatalog::parentColumnExists('genre')
            && (int) ($parent->genre ?? 0) === 0) {
            $genre = $this->bestIntFromGroups($groups, 'genre');
            if ($genre > 0) {
                $attributes['genre'] = $genre;
            }
        }

        foreach (['price', 'reseller_price', 'cost', 'cost_cnh'] as $field) {
            if (! ItemCatalog::parentColumnExists($field)) {
                continue;
            }

            if ((float) ($parent->{$field} ?? 0) > 0) {
                continue;
            }

            $value = $this->bestPriceFromGroupsAndItems($groups, $field);
            if ($value > 0) {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function firstNonEmptyFromGroups(Collection $groups, string $field): string
    {
        if (! ItemCatalog::groupColumnExists($field)) {
            return '';
        }

        foreach ($groups as $group) {
            $value = trim((string) ($group->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function firstNonEmptyFromItems(Collection $groups, string $field): string
    {
        if (! ItemCatalog::itemColumnExists($field)) {
            return '';
        }

        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                $value = trim((string) ($item->{$field} ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function bestIntFromGroups(Collection $groups, string $field): int
    {
        $best = 0;

        if (! ItemCatalog::groupColumnExists($field)) {
            return 0;
        }

        foreach ($groups as $group) {
            $best = max($best, (int) ($group->{$field} ?? 0));
        }

        return $best;
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function bestBrandFromGroups(Collection $groups): int
    {
        $best = 0;

        foreach ($groups as $group) {
            $best = max($best, $this->catalogBrandId($group->brand));
        }

        return $best;
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function bestPriceFromGroupsAndItems(Collection $groups, string $field): float
    {
        $best = 0.0;

        if (ItemCatalog::groupColumnExists($field)) {
            foreach ($groups as $group) {
                $best = max($best, (float) ($group->{$field} ?? 0));
            }
        }

        if (ItemCatalog::itemColumnExists($field)) {
            foreach ($groups as $group) {
                foreach ($group->items as $item) {
                    $best = max($best, (float) ($item->{$field} ?? 0));
                }
            }
        }

        return $best;
    }

    protected function sampleItemForGroup(ItemGroup $group): ?Item
    {
        return Item::query()
            ->where('group_id', $group->id)
            ->whereNull('deleted_at')
            ->with(['tags', 'group'])
            ->orderBy('id')
            ->first();
    }

    protected function catalogBrandId(mixed $brand): int
    {
        if ($brand instanceof ItemBrand) {
            return $brand->value;
        }

        return (int) ($brand ?? 0);
    }
}
