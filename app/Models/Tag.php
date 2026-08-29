<?php

namespace App\Models;

use App\Enums\ItemType;
use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Tag extends Model
{
    use HasFactory, FillsProductionColumnDefaults;

    protected $fillable = [
        'name',
        'code',
        'type',
        'item_type',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Constants from legacy logic
    const TYPE_NORMAL = 0;

    const TYPE_JAHIT = 2;

    const TYPE_TYPE = 3;

    const TYPE_SIZE = 7;

    const TYPE_COMPONENT = 8;

    const TYPE_MATERIAL = 9;

    const TYPE_VARIATION = 10;

    const TYPE_WARNA = 20;

    public static $types = [
        self::TYPE_NORMAL => 'Normal',
        self::TYPE_JAHIT => 'Jahit',
        self::TYPE_TYPE => 'Type',
        self::TYPE_SIZE => 'Size',
        self::TYPE_COMPONENT => 'Komponen',
        self::TYPE_MATERIAL => 'Material',
        self::TYPE_VARIATION => 'Variasi',
        self::TYPE_WARNA => 'Warna',
    ];

    public static function getPermissions(): array
    {
        return [
            'view' => 'stuff-tag-list',
            'create' => 'stuff-tag-create',
            'edit' => 'stuff-tag-edit',
            'delete' => 'stuff-tag-delete',
        ];
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_tag');
    }

    /**
     * Tags for item / asset lancar forms: item_type matches the form or is universal (0).
     *
     * @return Collection<int, Tag>
     */
    public static function tagsForItemForm(ItemType $itemType, int $tagType): Collection
    {
        return static::query()
            ->where('type', $tagType)
            ->whereIn('item_type', [0, $itemType->value])
            ->orderBy('name')
            ->get();
    }

    /**
     * TYPE tags (SKU prefix / restock tab) scoped to manufactured items or asset lancar.
     *
     * @return Collection<int, Tag>
     */
    public static function typeTagsForItem(ItemType $itemType): Collection
    {
        return static::tagsForItemForm($itemType, self::TYPE_TYPE);
    }

    /**
     * Manufactured TYPE tags for parser/converter (prefers item_type=1, includes legacy item_type=0).
     *
     * @return Collection<int, Tag>
     */
    public static function manufacturedTypeTags(): Collection
    {
        return static::query()
            ->where('type', self::TYPE_TYPE)
            ->whereIn('item_type', [ItemType::ITEM->value, 0])
            ->orderByDesc('item_type')
            ->get()
            ->unique(fn (Tag $tag) => strtoupper($tag->code))
            ->values();
    }

    public static function findManufacturedTypeTag(string $code): ?Tag
    {
        return static::query()
            ->where('type', self::TYPE_TYPE)
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->whereIn('item_type', [ItemType::ITEM->value, 0])
            ->orderByDesc('item_type')
            ->first();
    }

    public static function findSizeTag(string $code): ?self
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->where('type', self::TYPE_SIZE)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('UPPER(TRIM(code)) = ?', [$normalized])
                    ->orWhereRaw('UPPER(TRIM(name)) = ?', [$normalized]);
            })
            ->first();
    }

    public static function findWarnaTag(string $code, array $aliases = []): ?self
    {
        $candidates = array_values(array_unique(array_filter(array_map(
            fn (string $value) => strtoupper(trim($value)),
            array_merge([$code], $aliases),
        ))));

        foreach ($candidates as $normalized) {
            if ($normalized === '') {
                continue;
            }

            $found = static::query()
                ->where('type', self::TYPE_WARNA)
                ->where(function ($query) use ($normalized) {
                    $query->whereRaw('UPPER(TRIM(code)) = ?', [$normalized])
                        ->orWhereRaw('UPPER(TRIM(name)) = ?', [$normalized]);
                })
                ->first();

            if ($found) {
                return $found;
            }
        }

        return null;
    }

    public static function findOrCreateWarnaTag(string $code, array $aliases = []): self
    {
        $found = self::findWarnaTag($code, $aliases);

        if ($found) {
            return $found;
        }

        $normalized = strtoupper(trim($code));
        $attributes = self::normalizeWarnaAttributes([
            'type' => self::TYPE_WARNA,
            'name' => $normalized,
            'code' => $normalized,
            'item_type' => 0,
        ]);

        $owner = self::findByName($attributes['name']);

        if ($owner) {
            if ((int) $owner->type === self::TYPE_WARNA) {
                return $owner;
            }

            throw new InvalidArgumentException(
                "Warna tag {$normalized} not found; name already used by tag #{$owner->id} (type {$owner->type})."
            );
        }

        return self::createUniqueByName([
            'type' => self::TYPE_WARNA,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'item_type' => 0,
        ], self::TYPE_WARNA, 'Warna');
    }

    public static function findOrCreateSizeTag(string $code): self
    {
        $found = self::findSizeTag($code);

        if ($found) {
            return $found;
        }

        $normalized = strtoupper(trim($code));
        $owner = self::findByName($normalized);

        if ($owner) {
            if ((int) $owner->type === self::TYPE_SIZE) {
                return $owner;
            }

            throw new InvalidArgumentException(
                "Size tag {$normalized} not found; name already used by tag #{$owner->id} (type {$owner->type})."
            );
        }

        return self::createUniqueByName([
            'type' => self::TYPE_SIZE,
            'code' => $normalized,
            'name' => $normalized,
            'item_type' => 0,
        ], self::TYPE_SIZE, 'Size');
    }

    public static function findByName(string $name): ?self
    {
        $normalized = strtoupper(trim($name));

        if ($normalized === '') {
            return null;
        }

        return static::query()
            ->whereRaw('UPPER(TRIM(name)) = ?', [$normalized])
            ->first();
    }

    /**
     * @param  array{type: int, code: string, name: string, item_type: int}  $attributes
     */
    protected static function createUniqueByName(array $attributes, int $expectedType, string $label): self
    {
        $name = strtoupper(trim((string) $attributes['name']));

        try {
            return static::query()->create($attributes);
        } catch (UniqueConstraintViolationException $e) {
            $existing = self::findByName($name);

            if ($existing && (int) $existing->type === $expectedType) {
                return $existing;
            }

            if ($existing) {
                throw new InvalidArgumentException(
                    "{$label} tag {$name} not found; name already used by tag #{$existing->id} (type {$existing->type})."
                );
            }

            throw $e;
        }
    }

    /**
     * Item type used when linking from the tags list to a filtered index.
     */
    public function filterItemType(): ItemType
    {
        return (int) $this->item_type === ItemType::ASSET_LANCAR->value
            ? ItemType::ASSET_LANCAR
            : ItemType::ITEM;
    }

    /**
     * Item / asset lancar index URL filtered to items carrying this tag.
     */
    public function itemsIndexFilterUrl(?ItemType $itemType = null): string
    {
        $itemType ??= $this->filterItemType();
        $routeName = $itemType === ItemType::ASSET_LANCAR ? 'assetlancar.index' : 'items.index';

        $params = match ((int) $this->type) {
            self::TYPE_TYPE => ['item_type' => $this->id],
            self::TYPE_SIZE => ['size' => $this->id],
            self::TYPE_WARNA => ['warna' => $this->id],
            self::TYPE_JAHIT => ['jahit' => $this->id],
            default => ['tag_ids' => [$this->id]],
        };

        return route($routeName, $params);
    }

    /**
     * Warna tags use code identical to name (uppercase) for universal SKU generation.
     */
    public static function normalizeWarnaAttributes(array $attributes): array
    {
        if ((int) ($attributes['type'] ?? 0) === self::TYPE_WARNA) {
            $attributes['name'] = strtoupper(trim($attributes['name']));
            $attributes['code'] = $attributes['name'];
        }

        return $attributes;
    }

    protected static function stripDeletedTagFromItem(Item $item, int $tagId): void
    {
        $updates = [];

        $tagIds = array_values(array_filter(
            array_map('intval', explode(',', (string) $item->tag_ids)),
            fn (int $id) => $id !== $tagId,
        ));
        $updates['tag_ids'] = implode(',', $tagIds);

        if ((int) $item->genre === $tagId) {
            $updates['genre'] = 0;
        }

        if ((int) $item->size === $tagId) {
            $updates['size'] = 0;
        }

        $item->updateQuietly($updates);
    }

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if ((int) $tag->type === self::TYPE_WARNA) {
                $tag->name = strtoupper(trim($tag->name));
                $tag->code = $tag->name;
            }
        });

        static::deleting(function (Tag $tag) {
            $tagId = (int) $tag->id;

            $itemIds = DB::table('item_tag')
                ->where('tag_id', $tagId)
                ->pluck('item_id');

            $legacyItemIds = Item::query()
                ->where(function (Builder $query) use ($tagId) {
                    $query->where('tag_ids', (string) $tagId)
                        ->orWhere('tag_ids', 'like', "{$tagId},%")
                        ->orWhere('tag_ids', 'like', "%,{$tagId},%")
                        ->orWhere('tag_ids', 'like', "%,{$tagId}");
                })
                ->pluck('id');

            $itemIds = $itemIds->merge($legacyItemIds)->unique();

            DB::table('item_tag')->where('tag_id', $tagId)->delete();

            if ($itemIds->isNotEmpty()) {
                Item::query()
                    ->whereIn('id', $itemIds)
                    ->each(function (Item $item) use ($tagId) {
                        static::stripDeletedTagFromItem($item, $tagId);
                    });
            }

            Item::query()
                ->where(function (Builder $query) use ($tagId) {
                    $query->where('genre', $tagId)
                        ->orWhere('size', $tagId);
                })
                ->when($itemIds->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $itemIds))
                ->each(function (Item $item) use ($tagId) {
                    static::stripDeletedTagFromItem($item, $tagId);
                });
        });
    }
}
