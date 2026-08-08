<?php

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemGroup;
use App\Models\ItemIdentityConversionResult;
use App\Models\ItemIdentityConversionRun;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LegacyItemConverterService
{
    public const DEFAULT_BATCH_SIZE = 1000;

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

    public function eligibleQuery(ItemType $itemType): Builder
    {
        return $this->baseQuery($itemType)
            ->with(['tags', 'group'])
            ->where(function (Builder $query) {
                $query->where('items.created_at', '>=', now()->subYears(5))
                    ->orWhereExists($this->transactionDetailSinceSubquery(now()->subYears(2)));
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('item_identity_conversion_results')
                    ->whereColumn('item_identity_conversion_results.item_id', 'items.id')
                    ->where('item_identity_conversion_results.status', ItemIdentityConversionResult::STATUS_SUCCESS);
            })
            ->orderBy('id');
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
        DB::table('warehouse_items')->where('item_id', $item->id)->delete();
        $item->forceDelete();
    }

    protected function transactionDetailExistsSubquery(): \Closure
    {
        return function ($query) {
            $query->select(DB::raw(1))
                ->from('transaction_details')
                ->whereColumn('transaction_details.item_id', 'items.id')
                ->whereNull('transaction_details.deleted_at');
        };
    }

    protected function transactionDetailSinceSubquery(\DateTimeInterface $since): \Closure
    {
        $sinceDate = $since->format('Y-m-d');

        return function ($query) use ($sinceDate) {
            $query->select(DB::raw(1))
                ->from('transaction_details')
                ->whereColumn('transaction_details.item_id', 'items.id')
                ->whereNull('transaction_details.deleted_at')
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
    public function previewBatch(ItemType $itemType, int $limit = self::DEFAULT_BATCH_SIZE): Collection
    {
        $parser = $this->makeParser();
        $items = $this->eligibleQuery($itemType)->limit($limit)->get();

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
        $items = $this->eligibleQuery($itemType)->limit($limit)->get();

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
        $itemType = $item->type;
        $warnaTag = Tag::query()
            ->where('type', Tag::TYPE_WARNA)
            ->whereRaw('UPPER(code) = ?', [strtoupper((string) $parse->warnaCode)])
            ->firstOrFail();

        $sizeTag = null;

        if ($parse->sizeCode) {
            $sizeTag = Tag::query()
                ->where('type', Tag::TYPE_SIZE)
                ->whereRaw('UPPER(code) = ?', [strtoupper($parse->sizeCode)])
                ->first();
        }

        $typeTag = null;

        if ($itemType === ItemType::ITEM) {
            $typeTag = Tag::query()
                ->where('type', Tag::TYPE_TYPE)
                ->whereRaw('UPPER(code) = ?', [strtoupper((string) $parse->typeCode)])
                ->firstOrFail();

            $jahitTag = $item->tags->firstWhere('type', Tag::TYPE_JAHIT)
                ?? $item->tags()->where('type', Tag::TYPE_JAHIT)->first();

            if (! $jahitTag) {
                throw new \RuntimeException('JAHIT tag is required for manufactured items.');
            }
        }

        $group = $this->resolveGroup($itemType, (string) $parse->pcode, (string) $parse->groupName, $warnaTag);
        $canonicalCode = (string) $parse->canonicalCode;

        if (Item::query()->whereSku($canonicalCode)->where('id', '!=', $item->id)->exists()) {
            throw new \RuntimeException("Duplicate canonical SKU: {$canonicalCode}");
        }

        if ($itemType === ItemType::ITEM) {
            $this->preserveLegacyCode($item, $canonicalCode, $parse->legacyCode);
        }

        $item->group_id = $group->id;
        $item->pcode = strtoupper((string) $parse->pcode);
        $item->code = $canonicalCode;
        $item->name = $this->identityBuilder->buildName($group->name, $warnaTag, $sizeTag);
        $item->size = $sizeTag?->id ?? 0;
        $item->genre = $typeTag?->id ?? 0;
        $item->save();

        $tagIds = $this->collectTagIds($item, $typeTag, $warnaTag, $sizeTag);
        $item->tag_ids = implode(',', $tagIds);
        $item->save();
        $item->tags()->sync($tagIds);
    }

    protected function resolveGroup(ItemType $type, string $pcode, string $groupName, Tag $warnaTag): ItemGroup
    {
        $parsed = $this->identityBuilder->parsePcode($type, $pcode);
        $variant = $this->identityBuilder->groupVariant($type, $pcode, $warnaTag);

        $group = ItemGroup::query()->firstOrCreate(
            [
                'master' => $parsed['master'],
                'variant' => $variant,
            ],
            [
                'name' => strtoupper($groupName),
            ],
        );

        if (strtoupper(trim((string) $group->name)) === '' || $group->name === null) {
            $group->name = strtoupper($groupName);
            $group->save();
        }

        return $group;
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
        if ($item->group_id === null) {
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

        if ($item->type === ItemType::ITEM) {
            return $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_TYPE)
                && $item->tags->contains(fn (Tag $tag) => $tag->type === Tag::TYPE_JAHIT);
        }

        return true;
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

    protected function makeParser(): LegacyItemIdentityParser
    {
        return new LegacyItemIdentityParser($this->identityBuilder);
    }
}
