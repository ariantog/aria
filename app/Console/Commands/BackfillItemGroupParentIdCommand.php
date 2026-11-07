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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillItemGroupParentIdCommand extends Command
{
    protected $signature = 'items:backfill-group-parent-id
                            {--dry-run : Report changes without writing}
                            {--limit=0 : Max parents (copy-catalog) or colorway groups (link pass) to process (0 = all)}
                            {--parent-id= : With --copy-catalog, only this item_parents.id (verify parent_key in output)}
                            {--parent-key= : With --copy-catalog, target this item_parents.parent_key (e.g. 2:KNEESUPPORT-05)}
                            {--copy-catalog : Copy empty parent catalog fields from item_group legacy columns}';

    protected $description = 'Repair item_group.parent_id from SKU parent_key, then optionally copy legacy catalog onto item_parents.';

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

        $repairStats = $this->repairMislinkedParentIds($dryRun, $limit, $identityBuilder);
        $linked = $repairStats['repaired'];
        $parentsCreated = $repairStats['parents_created'];
        $groupsNeedingLink = $repairStats['scanned'];
        $catalogCopied = 0;

        if ($copyCatalog) {
            $relinked = $this->consolidateDuplicateParentKeys($dryRun);
            if ($relinked > 0) {
                $this->line("Consolidated colorway links onto canonical parent ids: {$relinked} group row(s)".($dryRun ? ' (dry-run)' : ''));
            }
            $this->reportDuplicateParentKeys();

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

        $this->info('Colorway groups scanned for parent_id repair: '.$groupsNeedingLink
            .'; parent_id fixed this run: '.$linked
            .'; parents created: '.$parentsCreated
            .($copyCatalog
                ? '; catalog parents updated: '.$catalogCopied.($dryRun ? ' (dry-run)' : '')
                : '')
            .($dryRun ? ' (dry-run)' : ''));
        $this->line("Colorway groups already on parent_id (>0): {$alreadyLinked}");
        $this->line("item_parents with ≥1 linked colorway: {$parentsWithGroups}");

        if ($groupsNeedingLink === 0 && $linked === 0 && (! $copyCatalog || $catalogCopied === 0)) {
            $this->comment(
                $copyCatalog
                    ? 'No parent catalog rows updated. Use --parent-key=2:KNEESUPPORT-05 (not a random parent id). Run repair output above for wrong parent_id on item_group.'
                    : 'No colorway groups to scan. Use --copy-catalog to lift legacy group catalog onto item_parents.'
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array{scanned: int, repaired: int, parents_created: int}
     */
    protected function repairMislinkedParentIds(bool $dryRun, int $limit, ItemIdentityBuilder $identityBuilder): array
    {
        $scanned = 0;
        $repaired = 0;
        $parentsCreated = 0;

        $query = ItemGroup::query()
            ->whereHas('items')
            ->with(['items' => fn ($q) => $q->with(['tags', 'group'])->orderBy('id')])
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->cursor() as $group) {
            $scanned++;

            $sample = $this->sampleItemPreferringActive(collect([$group]));
            if ($sample === null) {
                continue;
            }

            $sample->loadMissing(['group', 'tags']);
            $parentKey = $identityBuilder->itemParentKey($sample);
            $parent = ItemParent::query()->where('parent_key', $parentKey)->orderBy('id')->first();

            if ($parent === null) {
                if ($dryRun) {
                    $this->line("Would create item_parents for key {$parentKey} (group #{$group->id}, parent_id={$group->parent_id})");
                    $parentsCreated++;
                    $repaired++;

                    continue;
                }

                $parent = ItemPricing::ensureParentForItem($sample);
                $parentsCreated++;

                if ((int) $group->parent_id !== (int) $parent->id) {
                    $repaired++;
                }

                continue;
            }

            if ((int) $group->parent_id === (int) $parent->id) {
                continue;
            }

            if ($dryRun) {
                $this->line(
                    "Would repair group #{$group->id}: parent_id {$group->parent_id} → {$parent->id} ({$parentKey})"
                );
                $repaired++;

                continue;
            }

            ItemGroup::query()->whereKey($group->id)->update(['parent_id' => (int) $parent->id]);
            $repaired++;
        }

        return [
            'scanned' => $scanned,
            'repaired' => $repaired,
            'parents_created' => $parentsCreated,
        ];
    }

    protected function copyCatalogForLinkedParents(
        bool $dryRun,
        int $limit,
        ItemGroupHierarchyService $hierarchy,
        ItemIdentityBuilder $identityBuilder,
    ): int {
        $onlyParentId = max(0, (int) $this->option('parent-id'));
        $onlyParentKey = trim((string) $this->option('parent-key'));

        $parentQuery = ItemParent::query()->orderBy('id');

        if ($onlyParentKey !== '') {
            $parentQuery->where('parent_key', $onlyParentKey);
            $resolved = (clone $parentQuery)->orderBy('id')->first();
            if ($resolved === null) {
                $this->error("No item_parents row for parent_key \"{$onlyParentKey}\".");

                return 0;
            }
            $this->line("Targeting parent #{$resolved->id} ({$onlyParentKey}) — use parent_key, not an unrelated numeric id.");
        } elseif ($onlyParentId > 0) {
            $parentQuery->whereKey($onlyParentId);
            $resolved = ItemParent::query()->find($onlyParentId);
            if ($resolved !== null) {
                $this->line(
                    "Targeting parent #{$onlyParentId} (parent_key: ".($resolved->parent_key ?? '—').')'
                );
            }
        } else {
            $parentQuery->where(function ($query) {
                $query->whereNull('product_name')
                    ->orWhere('product_name', '')
                    ->orWhereNull('description')
                    ->orWhere('description', '');
            });
        }

        if ($limit > 0) {
            $parentQuery->limit($limit);
        }

        $updates = 0;
        $processedKeys = [];

        foreach ($parentQuery->get() as $parent) {
            $canonical = $this->canonicalParentFor($parent);
            $parentKey = trim((string) ($canonical->parent_key ?? ''));

            if ($parentKey !== '' && isset($processedKeys[$parentKey])) {
                continue;
            }

            if ($parentKey !== '') {
                $processedKeys[$parentKey] = true;
            }

            if ((int) $parent->id !== (int) $canonical->id) {
                $this->comment(
                    "Using canonical parent #{$canonical->id} for key {$parentKey} (skipped duplicate parent #{$parent->id})."
                );
            }

            $updates += $this->copyLegacyCatalogOntoParent($canonical, $dryRun, $hierarchy, $identityBuilder);
        }

        return $updates;
    }

    protected function copyLegacyCatalogOntoParent(
        ItemParent $parent,
        bool $dryRun,
        ItemGroupHierarchyService $hierarchy,
        ItemIdentityBuilder $identityBuilder,
    ): int {
        $parent = $this->canonicalParentFor($parent);
        $parent->refresh();

        $groups = $this->resolveCatalogGroupsForParent($parent, $hierarchy);

        if ($groups->isEmpty()) {
            $key = trim((string) ($parent->parent_key ?? ''));
            $this->warn(
                "Parent #{$parent->id} ({$key}): no colorway groups found by parent_id or parent_key — nothing to copy."
            );

            return 0;
        }

        if (! $dryRun) {
            $this->relinkGroupsToParent($groups, (int) $parent->id);
        } elseif ($groups->contains(fn (ItemGroup $g) => (int) $g->parent_id !== (int) $parent->id)) {
            $this->line("Would set parent_id={$parent->id} on colorway group id(s): ".$groups->pluck('id')->join(', '));
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
            $parentCatalogEmpty = trim((string) ($parent->product_name ?? '')) === ''
                && trim((string) ($parent->description ?? '')) === '';
            $this->line(
                'Parent #'.$parent->id.' ('.($parent->parent_key ?? '').'): groups '
                .$groups->pluck('id')->join(', ')
                .' — skip: '
                .($parentCatalogEmpty
                    ? 'colorway rows have no legacy catalog columns to lift (check item_group in SQL).'
                    : 'parent catalog columns already set.')
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

        if (! ItemCatalog::applyToParentRecord($parent, $attributes)) {
            $this->error("Failed to persist catalog on parent #{$parent->id}");

            return 0;
        }

        if (! $this->verifyParentCatalogPersisted($parent->id, array_keys($attributes))) {
            $this->error(
                "Save verification failed for parent #{$parent->id} — re-query still shows empty catalog. "
                .'Check you are viewing the same database/host as php artisan (.env).'
            );

            return 0;
        }

        $fresh = ItemParent::query()->find($parent->id);
        $title = trim((string) ($fresh?->product_name ?? ''));
        $descLen = strlen(trim((string) ($fresh?->description ?? '')));
        $this->line(
            "Copied catalog → parent #{$parent->id} from group id(s): {$groupIds}"
            .($title !== '' ? " — product_name: {$title}" : '')
            .($descLen > 0 ? ", description: {$descLen} chars" : '')
        );

        return 1;
    }

    protected function consolidateDuplicateParentKeys(bool $dryRun): int
    {
        if (! Schema::hasColumn('item_parents', 'parent_key')) {
            return 0;
        }

        $duplicateKeys = DB::table('item_parents')
            ->select('parent_key')
            ->whereNotNull('parent_key')
            ->where('parent_key', '!=', '')
            ->groupBy('parent_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('parent_key');

        $relinked = 0;

        foreach ($duplicateKeys as $parentKey) {
            $parentIds = ItemParent::query()
                ->where('parent_key', $parentKey)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($parentIds) < 2) {
                continue;
            }

            $canonicalId = $parentIds[0];

            foreach (array_slice($parentIds, 1) as $duplicateId) {
                $groupCount = ItemGroup::query()->where('parent_id', $duplicateId)->count();
                if ($groupCount === 0) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("Would relink {$groupCount} colorway(s): parent_id {$duplicateId} → {$canonicalId} ({$parentKey})");
                } else {
                    ItemGroup::query()
                        ->where('parent_id', $duplicateId)
                        ->update(['parent_id' => $canonicalId]);
                    $this->line("Relinked {$groupCount} colorway(s): parent_id {$duplicateId} → {$canonicalId} ({$parentKey})");
                }

                $relinked += $groupCount;
            }
        }

        return $relinked;
    }

    protected function reportDuplicateParentKeys(): void
    {
        $rows = DB::table('item_parents')
            ->select('parent_key', DB::raw('COUNT(*) as parent_count'), DB::raw('MIN(id) as canonical_id'))
            ->whereNotNull('parent_key')
            ->where('parent_key', '!=', '')
            ->groupBy('parent_key')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('parent_key')
            ->limit(15)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->warn('Duplicate item_parents.parent_key rows remain (orphans after relink). Canonical id = MIN(id):');
        foreach ($rows as $row) {
            $this->line("  {$row->parent_key}: {$row->parent_count} rows → use canonical #{$row->canonical_id}");
        }
    }

    protected function canonicalParentFor(ItemParent $parent): ItemParent
    {
        $parentKey = trim((string) ($parent->parent_key ?? ''));
        if ($parentKey === '') {
            return $parent;
        }

        return ItemParent::query()
            ->where('parent_key', $parentKey)
            ->orderBy('id')
            ->first() ?? $parent;
    }

    /**
     * @return list<int>
     */
    protected function siblingParentIds(ItemParent $parent): array
    {
        $parentKey = trim((string) ($parent->parent_key ?? ''));
        if ($parentKey === '') {
            return [(int) $parent->id];
        }

        return ItemParent::query()
            ->where('parent_key', $parentKey)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<string>  $attributeKeys
     */
    protected function verifyParentCatalogPersisted(int $parentId, array $attributeKeys): bool
    {
        $fresh = ItemParent::query()->find($parentId);
        if ($fresh === null) {
            return false;
        }

        foreach ($attributeKeys as $key) {
            if (in_array($key, ['product_name', 'description', 'description2', 'url'], true)) {
                if (trim((string) ($fresh->{$key} ?? '')) !== '') {
                    return true;
                }

                continue;
            }

            if (in_array($key, ['brand', 'genre'], true)) {
                if ((int) ($fresh->{$key} ?? 0) > 0) {
                    return true;
                }

                continue;
            }

            if (in_array($key, ['price', 'reseller_price', 'cost', 'cost_cnh'], true)) {
                if ((float) ($fresh->{$key} ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * @return Collection<int, ItemGroup>
     */
    protected function resolveCatalogGroupsForParent(ItemParent $parent, ItemGroupHierarchyService $hierarchy): Collection
    {
        $parentKey = trim((string) ($parent->parent_key ?? ''));
        $merged = collect();

        if ($parentKey !== '') {
            $merged = $merged->merge($hierarchy->groupsForParentKeyRelaxed($parentKey));
        }

        $merged = $merged->merge(
            ItemGroup::query()
                ->whereIn('parent_id', $this->siblingParentIds($parent))
                ->with(['items' => fn ($q) => $q->orderBy('id')])
                ->orderBy('id')
                ->get()
        );

        return $merged->unique('id')->values();
    }

    /**
     * @param  Collection<int, ItemGroup>  $groups
     */
    protected function relinkGroupsToParent(Collection $groups, int $parentId): void
    {
        $staleIds = $groups
            ->filter(fn (ItemGroup $group) => (int) $group->parent_id !== $parentId)
            ->pluck('id')
            ->all();

        if ($staleIds === []) {
            return;
        }

        ItemGroup::query()->whereIn('id', $staleIds)->update(['parent_id' => $parentId]);
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
