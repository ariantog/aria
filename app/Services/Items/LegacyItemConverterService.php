<?php

namespace App\Services\Items;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemIdentityConversionResult;
use App\Models\ItemIdentityConversionRun;
use App\Models\Tag;
use App\Models\User;
use App\Support\ItemCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyItemConverterService
{
    public const DEFAULT_BATCH_SIZE = 1000;

    public const PENDING_PAGE_SIZE = 500;

    public function __construct(
        protected ItemIdentityBuilder $identityBuilder,
    ) {}

    public function baseQuery(ItemType $itemType): Builder
    {
        return Item::query()
            ->whereNull('deleted_at')
            ->where('type', $itemType);
    }

    /**
     * Useless SKU: created >1 year ago and never appeared in any transaction detail.
     */
    public function uselessQuery(ItemType $itemType): Builder
    {
        return $this->baseQuery($itemType)
            ->where('created_at', '<', now()->subYear())
            ->whereNotExists($this->transactionDetailExistsSubquery());
    }

    /**
     * Super-old SKU: created >5 years ago with no transaction activity in the last 2 years.
     * Excluded from conversion queue (not deleted).
     */
    public function superOldQuery(ItemType $itemType): Builder
    {
        return $this->baseQuery($itemType)
            ->where('created_at', '<', now()->subYears(5))
            ->whereNotExists($this->transactionDetailSinceSubquery(now()->subYears(2)));
    }

    public function candidateQuery(ItemType $itemType, bool $withRelations = false): Builder
    {
        $query = $this->candidateBaseQuery($itemType);

        if ($withRelations) {
            $query->with(['tags', 'group']);
        }

        return $query;
    }

    protected function candidateBaseQuery(ItemType $itemType): Builder
    {
        return $this->baseQuery($itemType)
            ->where(function (Builder $query) {
                $query->where('items.created_at', '>=', now()->subYears(5))
                    ->orWhereExists($this->transactionDetailSinceSubquery(now()->subYears(2)));
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('item_identity_conversion_results')
                    ->whereColumn('item_identity_conversion_results.item_id', 'items.id')
                    ->whereIn('item_identity_conversion_results.status', [
                        ItemIdentityConversionResult::STATUS_SUCCESS,
                        ItemIdentityConversionResult::STATUS_SKIPPED,
                    ]);
            })
            ->where(fn (Builder $query) => $this->applyPendingConversionScope($query))
            ->orderByDesc('items.id');
    }

    /**
     * Items still needing conversion: no preserved legacy SKU (empty legacy_code, or legacy still equals code).
     */
    public function hasPreservedLegacyCode(Item $item): bool
    {
        $legacy = strtoupper(trim((string) ($item->legacy_code ?? '')));
        $code = strtoupper(trim((string) ($item->code ?? '')));

        return $legacy !== '' && $legacy !== $code;
    }

    public function isPendingConversion(Item $item): bool
    {
        return ! $this->hasPreservedLegacyCode($item);
    }

    /**
     * Legacy rows use group_id 0 for "no group"; only positive IDs are real groups.
     */
    public function hasProductGroup(Item $item): bool
    {
        return (int) $item->group_id > 0;
    }

    protected function applyPendingConversionScope(Builder $query): void
    {
        $query->where(function (Builder $pending) {
            $pending->whereNull('items.legacy_code')
                ->orWhere('items.legacy_code', '')
                ->orWhereColumn('items.legacy_code', 'items.code');
        });
    }

    /** @deprecated Use candidateQuery() or countEligible() */
    public function eligibleQuery(ItemType $itemType): Builder
    {
        return $this->candidateQuery($itemType);
    }

    /**
     * True when the item belongs on the legacy converter queue (parseable legacy row, not already canonical).
     */
    public function isStructurallyEligible(Item $item, ?LegacyItemIdentityParser $parser = null): bool
    {
        $parser ??= $this->makeParser();

        $item->loadMissing(['tags', 'group']);

        $itemType = $parser->resolveItemType($item);

        if ($itemType === null) {
            return false;
        }

        if (! $this->isPendingConversion($item)) {
            return false;
        }

        if (! $parser->hasMinimumIdentityStructure((string) $item->code, $itemType)) {
            return false;
        }

        // Ungrouped legacy rows cannot be fully canonical — skip the expensive parse pass.
        if (! $this->hasProductGroup($item)) {
            return true;
        }

        $parse = $parser->parse($item);

        return ! ($parse->success && $this->isAlreadyCanonical($item, $parse));
    }

    /**
     * @return array{eligible: int, unparseable: int, candidates: int}
     */
    public function queueStats(ItemType $itemType): array
    {
        $parser = $this->makeParser();
        $eligible = 0;
        $unparseable = 0;
        $candidates = 0;

        $this->candidateBaseQuery($itemType)
            ->with(['tags', 'group'])
            ->select(['items.id', 'items.code', 'items.type', 'items.group_id', 'items.legacy_code'])
            ->chunkByIdDesc(500, function ($items) use ($parser, $itemType, &$eligible, &$unparseable, &$candidates) {
                foreach ($items as $item) {
                    $candidates++;

                    if ($this->isStructurallyEligible($item, $parser)) {
                        $eligible++;
                    } elseif (! $parser->hasMinimumIdentityStructure((string) $item->code, $itemType)) {
                        $unparseable++;
                    }
                }
            }, 'id');

        return compact('eligible', 'unparseable', 'candidates');
    }

    public function countEligible(ItemType $itemType): int
    {
        return $this->queueStats($itemType)['eligible'];
    }

    public function countStructurallyUnparseable(ItemType $itemType): int
    {
        return $this->queueStats($itemType)['unparseable'];
    }

    public function countCandidates(ItemType $itemType): int
    {
        return $this->queueStats($itemType)['candidates'];
    }

    public function paginateEligible(
        ItemType $itemType,
        int $perPage = self::PENDING_PAGE_SIZE,
        ?int $total = null,
    ): LengthAwarePaginator {
        $page = max(1, (int) request()->query('page', 1));

        if ($total === null) {
            return $this->pendingIndexData($itemType, $perPage, $page)['paginator'];
        }

        $pageItems = $this->eligibleItemsForPage($itemType, $page, $perPage);

        return new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * Single-pass pending tab data: queue stats + paginated eligible items.
     *
     * @return array{
     *     stats: array{eligible: int, unparseable: int, candidates: int},
     *     paginator: LengthAwarePaginator
     * }
     */
    public function pendingIndexData(
        ItemType $itemType,
        int $perPage = self::PENDING_PAGE_SIZE,
        ?int $page = null,
    ): array {
        $parser = $this->makeParser();
        $page = max(1, $page ?? (int) request()->query('page', 1));
        $start = ($page - 1) * $perPage;
        $eligible = 0;
        $unparseable = 0;
        $candidates = 0;
        $eligibleIndex = 0;
        $pageItemIds = [];

        $this->candidateBaseQuery($itemType)
            ->with(['tags', 'group'])
            ->select(['items.id', 'items.code', 'items.type', 'items.group_id', 'items.legacy_code'])
            ->chunkByIdDesc(500, function ($items) use (
                $parser,
                $itemType,
                $start,
                $perPage,
                &$eligible,
                &$unparseable,
                &$candidates,
                &$eligibleIndex,
                &$pageItemIds,
            ) {
                foreach ($items as $item) {
                    $candidates++;

                    if ($this->isStructurallyEligible($item, $parser)) {
                        if ($eligibleIndex >= $start && count($pageItemIds) < $perPage) {
                            $pageItemIds[] = $item->id;
                        }

                        $eligibleIndex++;
                        $eligible++;
                    } elseif (! $parser->hasMinimumIdentityStructure((string) $item->code, $itemType)) {
                        $unparseable++;
                    }
                }

                return count($pageItemIds) < $perPage;
            }, 'id');

        $pageItems = $pageItemIds === []
            ? collect()
            : $this->candidateQuery($itemType, withRelations: true)
                ->whereIn('items.id', $pageItemIds)
                ->get()
                ->sortBy(fn (Item $item) => array_search($item->id, $pageItemIds, true))
                ->values();

        return [
            'stats' => compact('eligible', 'unparseable', 'candidates'),
            'paginator' => new LengthAwarePaginator(
                $pageItems,
                $eligible,
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            ),
        ];
    }

    /**
     * Eligible pending items for a single list page (same rows the UI shows).
     *
     * @return Collection<int, Item>
     */
    public function eligibleItemsForPage(ItemType $itemType, int $page, int $perPage = self::PENDING_PAGE_SIZE): Collection
    {
        $parser = $this->makeParser();
        $page = max(1, $page);
        $start = ($page - 1) * $perPage;
        $eligibleIndex = 0;
        $pageItemIds = [];

        $this->candidateBaseQuery($itemType)
            ->with(['tags', 'group'])
            ->select(['items.id', 'items.code', 'items.type', 'items.group_id', 'items.legacy_code'])
            ->chunkByIdDesc(500, function ($items) use (
                $parser,
                $start,
                $perPage,
                &$eligibleIndex,
                &$pageItemIds,
            ) {
                foreach ($items as $item) {
                    if (! $this->isStructurallyEligible($item, $parser)) {
                        continue;
                    }

                    if ($eligibleIndex >= $start && count($pageItemIds) < $perPage) {
                        $pageItemIds[] = $item->id;
                    }

                    $eligibleIndex++;
                }

                return count($pageItemIds) < $perPage;
            }, 'id');

        if ($pageItemIds === []) {
            return collect();
        }

        return $this->candidateQuery($itemType, withRelations: true)
            ->whereIn('items.id', $pageItemIds)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, $pageItemIds, true))
            ->values();
    }

    /**
     * @return Collection<int, Item>
     */
    public function nextEligibleBatch(ItemType $itemType, int $limit = self::DEFAULT_BATCH_SIZE): Collection
    {
        $parser = $this->makeParser();
        $batchIds = [];

        $this->candidateBaseQuery($itemType)
            ->with(['tags', 'group'])
            ->select(['items.id', 'items.code', 'items.type', 'items.group_id', 'items.legacy_code'])
            ->chunkByIdDesc(500, function ($items) use ($parser, $limit, &$batchIds) {
                foreach ($items as $item) {
                    if (! $this->isStructurallyEligible($item, $parser)) {
                        continue;
                    }

                    $batchIds[] = $item->id;

                    if (count($batchIds) >= $limit) {
                        return false;
                    }
                }

                return count($batchIds) < $limit;
            }, 'id');

        if ($batchIds === []) {
            return collect();
        }

        return $this->candidateQuery($itemType, withRelations: true)
            ->whereIn('items.id', $batchIds)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, $batchIds, true))
            ->values();
    }

    public function deleteUselessBatch(ItemType $itemType, int $limit = self::DEFAULT_BATCH_SIZE): int
    {
        $items = $this->uselessQuery($itemType)->orderBy('id')->limit($limit)->get();
        $deleted = 0;

        foreach ($items as $item) {
            DB::transaction(fn () => $this->hardDeleteItem($item));
            $deleted++;
        }

        return $deleted;
    }

    protected function hardDeleteItem(Item $item): void
    {
        ItemIdentityConversionResult::query()->where('item_id', $item->id)->delete();
        $item->tags()->detach();
        DB::table('warehouse_item')->where('item_id', $item->id)->delete();
        $item->forceDelete();
    }

    protected function transactionDetailExistsSubquery(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('transaction_details')
                ->whereColumn('transaction_details.item_id', 'items.id');
        };
    }

    protected function transactionDetailSinceSubquery(\DateTimeInterface $since): \Closure
    {
        $sinceDate = $since->format('Y-m-d');

        return function ($query) use ($sinceDate) {
            $query->select(DB::raw(1))
                ->from('transaction_details')
                ->whereColumn('transaction_details.item_id', 'items.id')
                ->where(function ($dateQuery) use ($sinceDate) {
                    $dateQuery->where('transaction_details.date', '>=', $sinceDate)
                        ->orWhereIn('transaction_details.transaction_id', function ($tx) use ($sinceDate) {
                            $tx->select('id')
                                ->from('transactions')
                                ->where('date', '>=', $sinceDate);
                        });
                });
        };
    }

    /**
     * @return Collection<int, array{item: Item, parse: LegacyParseResult}>
     */
    public function previewItems(Collection $items): Collection
    {
        $parser = $this->makeParser();

        return $items
            ->map(fn (Item $item) => $item->loadMissing(['tags', 'group']))
            ->map(fn (Item $item) => [
                'item' => $item,
                'parse' => $parser->parse($item),
            ]);
    }

    public function runItems(
        ItemType $itemType,
        Collection $items,
        User $user,
        bool $dryRun = false,
    ): ItemIdentityConversionRun {
        $parser = $this->makeParser();
        $items = $items
            ->filter(fn (Item $item) => $parser->resolveItemType($item) === $itemType)
            ->filter(fn (Item $item) => $this->isPendingConversion($item))
            ->filter(fn (Item $item) => $this->isStructurallyEligible($item, $parser))
            ->values();

        $run = ItemIdentityConversionRun::query()->create([
            'item_type' => $itemType,
            'dry_run' => $dryRun,
            'batch_size' => $items->count(),
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        foreach ($items as $item) {
            $this->convertItem($item, $run, $parser, $dryRun);
        }

        $run->update(['finished_at' => now()]);

        return $run->fresh(['results']);
    }

    /**
     * @return Collection<int, array{item: Item, parse: LegacyParseResult}>
     */
    public function previewPage(ItemType $itemType, int $page, int $perPage = self::PENDING_PAGE_SIZE): Collection
    {
        return $this->previewItems(
            $this->eligibleItemsForPage($itemType, $page, $perPage),
        );
    }

    public function runPage(
        ItemType $itemType,
        User $user,
        int $page,
        int $perPage = self::PENDING_PAGE_SIZE,
        bool $dryRun = false,
    ): ItemIdentityConversionRun {
        return $this->runItems(
            $itemType,
            $this->eligibleItemsForPage($itemType, $page, $perPage),
            $user,
            $dryRun,
        );
    }

    /**
     * @return Collection<int, Item>
     */
    public function itemsForIds(ItemType $itemType, array $itemIds): Collection
    {
        if ($itemIds === []) {
            return collect();
        }

        return $this->candidateQuery($itemType, withRelations: true)
            ->whereIn('items.id', $itemIds)
            ->get()
            ->sortBy(fn (Item $item) => array_search($item->id, $itemIds, true))
            ->values();
    }

    /**
     * @return Collection<int, array{item: Item, parse: LegacyParseResult}>
     */
    public function previewBatch(ItemType $itemType, int $limit = self::DEFAULT_BATCH_SIZE): Collection
    {
        $parser = $this->makeParser();
        $items = $this->nextEligibleBatch($itemType, $limit);

        return $items->map(fn (Item $item) => [
            'item' => $item,
            'parse' => $parser->parse($item),
        ]);
    }

    public function runBatch(ItemType $itemType, User $user, bool $dryRun = false, int $limit = self::DEFAULT_BATCH_SIZE): ItemIdentityConversionRun
    {
        $run = ItemIdentityConversionRun::query()->create([
            'item_type' => $itemType,
            'dry_run' => $dryRun,
            'batch_size' => $limit,
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        $parser = $this->makeParser();
        $items = $this->nextEligibleBatch($itemType, $limit);

        foreach ($items as $item) {
            $this->convertItem($item, $run, $parser, $dryRun);
        }

        $run->update(['finished_at' => now()]);

        return $run->fresh(['results']);
    }

    public function convertItem(
        Item $item,
        ItemIdentityConversionRun $run,
        ?LegacyItemIdentityParser $parser = null,
        bool $dryRun = false,
    ): ItemIdentityConversionResult {
        $parser ??= $this->makeParser();
        $parse = $parser->parse($item->fresh(['tags', 'group']));

        return $this->convertWithParse($item, $run, $parse, $dryRun);
    }

    public function convertWithParse(
        Item $item,
        ItemIdentityConversionRun $run,
        LegacyParseResult $parse,
        bool $dryRun = false,
    ): ItemIdentityConversionResult {
        if (! $parse->success) {
            return $this->recordResult($run, $item, ItemIdentityConversionResult::STATUS_FAILED, $parse, $dryRun);
        }

        if ($this->isAlreadyCanonical($item, $parse)) {
            return $this->recordResult($run, $item, ItemIdentityConversionResult::STATUS_SKIPPED, $parse, $dryRun);
        }

        if ($dryRun) {
            return $this->recordResult($run, $item, ItemIdentityConversionResult::STATUS_SUCCESS, $parse, true);
        }

        try {
            DB::transaction(function () use ($item, $parse) {
                $this->applyParse($item, $parse);
            });

            return $this->recordResult($run, $item, ItemIdentityConversionResult::STATUS_SUCCESS, $parse, false);
        } catch (\Throwable $e) {
            $failureCode = match (true) {
                str_contains($e->getMessage(), 'JAHIT') => 'JAHIT_MISSING',
                str_contains($e->getMessage(), 'Duplicate canonical') => 'DUPLICATE_CANONICAL',
                str_contains($e->getMessage(), 'Data too long')
                    || str_contains($e->getMessage(), '22001') => LegacyItemIdentityParser::FAILURE_GROUP_NAME_TOO_LONG,
                default => LegacyItemIdentityParser::FAILURE_SKU_UNPARSEABLE,
            };

            $failure = LegacyParseResult::failure(
                $failureCode,
                $e->getMessage(),
                $parse->snapshot,
            );

            return $this->recordResult($run, $item, ItemIdentityConversionResult::STATUS_FAILED, $failure, false);
        }
    }

    protected function applyParse(Item $item, LegacyParseResult $parse): void
    {
        $itemType = $this->makeParser()->resolveItemType($item);
        $warnaTag = Tag::findWarnaTag((string) $parse->warnaCode);

        if (! $warnaTag) {
            throw new \RuntimeException("Warna tag not found: {$parse->warnaCode}");
        }

        $sizeTag = $parse->sizeCode
            ? Tag::findSizeTag((string) $parse->sizeCode)
            : null;

        $typeTag = null;
        $assetTypeTag = null;

        if ($itemType === ItemType::ITEM) {
            $typeTag = Tag::findManufacturedTypeTag((string) $parse->typeCode);

            if (! $typeTag) {
                throw new \RuntimeException("TYPE tag not found for manufactured item: {$parse->typeCode}");
            }

            $jahitTag = $item->tags->firstWhere('type', Tag::TYPE_JAHIT)
                ?? $item->tags()->where('type', Tag::TYPE_JAHIT)->first();

            if (! $jahitTag) {
                throw new \RuntimeException('JAHIT tag is required for manufactured items.');
            }
        } elseif ($itemType === ItemType::ASSET_LANCAR) {
            $assetTypeTag = $this->resolveAssetLancarTypeTag($item);
        }

        $effectiveTypeTag = $typeTag ?? $assetTypeTag;

        $item->loadMissing('group');
        $previousGroup = $item->group;

        $group = $this->resolveGroup($itemType, (string) $parse->pcode, (string) $parse->groupName, $warnaTag);
        $canonicalCode = (string) $parse->canonicalCode;

        if (Item::query()->whereSku($canonicalCode)->where('id', '!=', $item->id)->exists()) {
            throw new \RuntimeException("Duplicate canonical SKU: {$canonicalCode}");
        }

        $this->preserveLegacyCode($item, $canonicalCode, $parse->legacyCode);

        $item->group_id = $group->id;
        $item->pcode = strtoupper((string) $parse->pcode);
        $item->code = $canonicalCode;
        $item->name = $this->identityBuilder->buildName((string) $parse->groupName, $warnaTag, $sizeTag);
        $item->size = $sizeTag?->id ?? 0;
        $this->persistConvertedCatalog($item, $group, $previousGroup, (string) $parse->pcode, $effectiveTypeTag);
        $item->save();

        $tagIds = $this->collectTagIds($item, $effectiveTypeTag, $warnaTag, $sizeTag);
        $item->tag_ids = implode(',', $tagIds);
        $item->save();
        $item->tags()->sync($tagIds);
    }

    /**
     * Write shared catalog fields to the group (source of truth) and mirror leftovers.
     * Existing group description is kept; leftover item text only seeds an empty group.
     */
    protected function persistConvertedCatalog(
        Item $item,
        ItemGroup $group,
        ?ItemGroup $previousGroup,
        string $pcode,
        ?Tag $typeTag,
    ): void {
        $attributes = [];
        $brand = ItemBrand::fromPcode($pcode);

        if ($brand !== ItemBrand::NO_BRAND) {
            $attributes['brand'] = $brand;
        }

        $genre = (int) ($typeTag?->id ?? 0);

        if ($genre > 0) {
            $attributes['genre'] = $genre;
        }

        if ($attributes !== []) {
            ItemCatalog::applyToGroup($group, $attributes);
        }

        $sourceGroup = $previousGroup && (int) $previousGroup->id !== (int) $group->id
            ? $previousGroup
            : null;

        ItemCatalog::seedEmptyDescriptions($group, $item, $sourceGroup);

        ItemCatalog::mirrorToItem($item, [
            'description' => $group->description ?? '',
            'description2' => $group->description2 ?? '',
            'brand' => $group->brand,
            'genre' => (int) ($group->genre ?? 0),
        ]);

        $item->setRelation('group', $group);
    }

    protected function resolveGroup(ItemType $type, string $pcode, string $groupName, Tag $warnaTag): ItemGroup
    {
        $groupMaster = $this->identityBuilder->groupMaster($type, $pcode);
        $variant = $this->identityBuilder->groupVariant($type, $pcode, $warnaTag);
        $storedName = $this->identityBuilder->uniqueStoredGroupName(
            $this->identityBuilder->storedGroupName($type, $groupName, $pcode, $variant),
            $groupMaster,
            $variant,
        );

        $group = $this->identityBuilder->findCanonicalGroup($groupMaster, $variant)
            ?? ItemGroup::query()->firstOrCreate(
            [
                'master' => $groupMaster,
                'variant' => $variant,
            ],
            [
                'name' => $storedName,
            ],
        );

        if (strtoupper(trim((string) $group->name)) !== strtoupper($storedName)) {
            $group->name = $storedName;
        }

        if (strtoupper(trim((string) ($group->master ?? ''))) !== strtoupper($groupMaster)) {
            $group->master = $groupMaster;
        }

        if (strtoupper(trim((string) ($group->variant ?? ''))) !== strtoupper($variant)) {
            $group->variant = $variant;
        }

        if ($group->isDirty()) {
            $group->save();
        }

        return $group;
    }

    protected function resolveAssetLancarTypeTag(Item $item): ?Tag
    {
        $fromTags = $item->tags->first(
            fn (Tag $tag) => (int) $tag->type === Tag::TYPE_TYPE
                && (int) $tag->item_type === ItemType::ASSET_LANCAR->value,
        );

        if ($fromTags) {
            return $fromTags;
        }

        $item->loadMissing('group');
        $genreId = $item->catalogGenre();

        if ($genreId <= 0) {
            return null;
        }

        $fromGenre = Tag::query()->find($genreId);

        if ($fromGenre
            && (int) $fromGenre->type === Tag::TYPE_TYPE
            && (int) $fromGenre->item_type === ItemType::ASSET_LANCAR->value) {
            return $fromGenre;
        }

        return null;
    }

    protected function preserveLegacyCode(Item $item, string $newCode, ?string $explicitLegacy = null): void
    {
        if (trim((string) ($item->legacy_code ?? '')) !== '') {
            return;
        }

        if ($explicitLegacy !== null && trim($explicitLegacy) !== '') {
            $item->legacy_code = strtoupper(trim($explicitLegacy));

            return;
        }

        $currentCode = strtoupper(trim((string) ($item->code ?? '')));
        $newCode = strtoupper(trim($newCode));

        if ($currentCode !== '' && $currentCode !== $newCode) {
            $item->legacy_code = $currentCode;
        }
    }

    /**
     * @return array<int, int>
     */
    protected function collectTagIds(Item $item, ?Tag $typeTag, Tag $warnaTag, ?Tag $sizeTag): array
    {
        $jahitIds = $item->tags
            ->where('type', Tag::TYPE_JAHIT)
            ->pluck('id')
            ->all();

        $tagIds = array_merge(
            $jahitIds,
            array_filter([
                $typeTag?->id,
                $sizeTag?->id,
                $warnaTag->id,
            ]),
        );

        $tagIds = array_unique(array_filter($tagIds));
        sort($tagIds);

        return $tagIds;
    }

    protected function isAlreadyCanonical(Item $item, LegacyParseResult $parse): bool
    {
        if (! $this->hasProductGroup($item)) {
            return false;
        }

        if (strtoupper(trim((string) $item->code)) !== strtoupper(trim((string) $parse->canonicalCode))) {
            return false;
        }

        $hasWarna = $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_WARNA);
        $hasSize = $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_SIZE)
            || strtoupper((string) ($parse->sizeCode ?? '')) === ItemIdentityBuilder::ALL_SIZE_CODE;

        if (! $hasWarna || ! $hasSize) {
            return false;
        }

        $itemType = $this->makeParser()->resolveItemType($item);

        if ($itemType === ItemType::ITEM) {
            return $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_TYPE)
                && $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_JAHIT)
                && $this->itemLinkedToExpectedGroup($item, $parse, $itemType);
        }

        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->itemLinkedToExpectedGroup($item, $parse, $itemType)
                && $this->resolveAssetLancarTypeTag($item) !== null;
        }

        return true;
    }

    protected function itemLinkedToExpectedGroup(Item $item, LegacyParseResult $parse, ItemType $itemType): bool
    {
        $group = $item->group;

        if (! $group) {
            return false;
        }

        $groupMaster = strtoupper(trim((string) ($group->master ?? '')));
        if ($groupMaster === '') {
            return false;
        }

        $parsed = $this->identityBuilder->parsePcode($itemType, (string) $parse->pcode);
        $warnaTag = Tag::findWarnaTag((string) $parse->warnaCode);
        $expectedVariant = $this->identityBuilder->groupVariant($itemType, (string) $parse->pcode, $warnaTag);

        return strtoupper(trim((string) $group->master)) === strtoupper(trim((string) $parsed['master']))
            && strtoupper(trim((string) $group->variant)) === strtoupper(trim($expectedVariant));
    }

    protected function recordResult(
        ItemIdentityConversionRun $run,
        Item $item,
        string $status,
        LegacyParseResult $parse,
        bool $dryRun,
    ): ItemIdentityConversionResult {
        $result = ItemIdentityConversionResult::query()->create([
            'run_id' => $run->id,
            'item_id' => $item->id,
            'status' => $status,
            'failure_code' => $parse->failureCode,
            'detail' => $parse->detail,
            'snapshot' => array_merge($parse->snapshot, [
                'dry_run' => $dryRun,
                'canonical_code' => $parse->canonicalCode,
                'pcode' => $parse->pcode,
                'warna_code' => $parse->warnaCode,
                'size_code' => $parse->sizeCode,
                'type_code' => $parse->typeCode,
                'original_code' => $item->code,
            ]),
        ]);

        $run->increment('processed_count');

        match ($status) {
            ItemIdentityConversionResult::STATUS_SUCCESS => $run->increment('success_count'),
            ItemIdentityConversionResult::STATUS_FAILED => $run->increment('failed_count'),
            ItemIdentityConversionResult::STATUS_SKIPPED => $run->increment('skipped_count'),
            default => null,
        };

        return $result;
    }

    /**
     * Detail-page convert panel: items not yet fully canonical (group link, tags, SKU).
     *
     * @return array{
     *     visible: bool,
     *     convertible: bool,
     *     parse: ?LegacyParseResult,
     *     message: ?string,
     *     item_type: ?ItemType
     * }
     */
    public function detailConvertContext(Item $item): array
    {
        $parser = $this->makeParser();
        $itemType = $parser->resolveItemType($item);
        $hidden = [
            'visible' => false,
            'convertible' => false,
            'parse' => null,
            'message' => null,
            'item_type' => $itemType,
        ];

        if (! in_array($itemType, [ItemType::ITEM, ItemType::ASSET_LANCAR], true)) {
            return array_merge($hidden, ['message' => 'Unsupported item type for identity conversion.']);
        }

        $item->loadMissing(['tags', 'group']);
        $parse = $parser->parse($item);

        if ($parse->success && $this->isDetailConversionComplete($item, $parse)) {
            return array_merge($hidden, ['message' => 'Item is already converted and linked to its product group.']);
        }

        if (! $parse->success) {
            return [
                'visible' => true,
                'convertible' => false,
                'parse' => $parse,
                'message' => $parse->detail ?? 'SKU cannot be parsed for conversion.',
                'item_type' => $itemType,
            ];
        }

        return [
            'visible' => true,
            'convertible' => true,
            'parse' => $parse,
            'message' => $this->detailConversionRepairMessage($item, $parse, $itemType),
            'item_type' => $itemType,
            'can_convert' => true,
        ];
    }

    /**
     * Detail panel hides only when SKU, group link, tags, and asset TYPE tag are all complete.
     */
    public function isDetailConversionComplete(Item $item, LegacyParseResult $parse): bool
    {
        if (! $parse->success || ! $this->isAlreadyCanonical($item, $parse)) {
            return false;
        }

        $itemType = $this->makeParser()->resolveItemType($item);

        if ($itemType === ItemType::ASSET_LANCAR) {
            return $this->resolveAssetLancarTypeTag($item) !== null;
        }

        return true;
    }

    protected function detailConversionRepairMessage(Item $item, LegacyParseResult $parse, ItemType $itemType): ?string
    {
        if ($this->isDetailConversionComplete($item, $parse)) {
            return null;
        }

        if (! $this->hasProductGroup($item)) {
            return 'Item is not linked to a product group yet. Converting will create or reuse the correct group.';
        }

        if (! $this->itemLinkedToExpectedGroup($item, $parse, $itemType)) {
            $parsed = $this->identityBuilder->parsePcode($itemType, (string) $parse->pcode);
            $warnaTag = Tag::findWarnaTag((string) $parse->warnaCode);
            $expectedVariant = $this->identityBuilder->groupVariant($itemType, (string) $parse->pcode, $warnaTag);

            return 'Item is linked to the wrong product group (expected '
                .$parsed['master'].' / '.$expectedVariant.'). Converting will relink it.';
        }

        if ($itemType === ItemType::ASSET_LANCAR && $this->resolveAssetLancarTypeTag($item) === null) {
            return 'Item is missing the asset TYPE tag (e.g. GLOVE) required for restock. Converting will restore it.';
        }

        return 'Item identity is incomplete. Converting will finish group link, tags, and SKU.';
    }

    public function convertSingleFromDetail(Item $item, User $user): ItemIdentityConversionResult
    {
        $context = $this->detailConvertContext($item);

        if (! $context['visible'] || ! $context['convertible'] || ! $context['parse']?->success) {
            throw new \RuntimeException($context['message'] ?? 'Item is not eligible for conversion.');
        }

        $itemType = $context['item_type'] ?? ItemType::ITEM;

        return $this->runItem($item, $itemType, $user, forDetailRepair: true);
    }

    public function runItem(Item $item, ItemType $itemType, User $user, bool $forDetailRepair = false): ItemIdentityConversionResult
    {
        $parser = $this->makeParser();
        $item = $item->fresh(['tags', 'group']);

        if ($parser->resolveItemType($item) !== $itemType) {
            throw new \RuntimeException('Item type does not match the selected converter tab.');
        }

        if ($forDetailRepair) {
            $parse = $parser->parse($item);

            if (! $parse->success || $this->isDetailConversionComplete($item, $parse)) {
                throw new \RuntimeException('Item is not eligible for legacy conversion.');
            }
        } elseif (! $this->isStructurallyEligible($item, $parser)) {
            throw new \RuntimeException('Item is not eligible for legacy conversion.');
        }

        $run = ItemIdentityConversionRun::query()->create([
            'item_type' => $itemType,
            'dry_run' => false,
            'batch_size' => 1,
            'user_id' => $user->id,
            'started_at' => now(),
        ]);

        $result = $this->convertItem($item->fresh(['tags', 'group']), $run, $parser, false);
        $run->update(['finished_at' => now()]);

        return $result;
    }

    protected function makeParser(): LegacyItemIdentityParser
    {
        return new LegacyItemIdentityParser($this->identityBuilder);
    }
}
