<?php

namespace App\Models;

use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Tag extends Model
{
    use HasFactory;

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
     * TYPE tags (SKU prefix / restock tab) scoped to manufactured items or asset lancar.
     *
     * @return Collection<int, Tag>
     */
    public static function typeTagsForItem(ItemType $itemType): Collection
    {
        return static::query()
            ->where('type', self::TYPE_TYPE)
            ->where('item_type', $itemType->value)
            ->orderBy('name')
            ->get();
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

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if ((int) $tag->type === self::TYPE_WARNA) {
                $tag->name = strtoupper(trim($tag->name));
                $tag->code = $tag->name;
            }
        });
    }
}
