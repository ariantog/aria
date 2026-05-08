<?php

namespace App\Models;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'name',
        'code',
        'pcode',
        'brand',
        'type',
        'size',
        'genre',
        'price',
        'cost',
        'qty',
        'tag_ids',
        'description',
        'description2',
        'url',
        'jubelio_item_id',
    ];

    protected function casts(): array
    {
        return [
            'brand' => ItemBrand::class,
            'type' => ItemType::class,
            'size' => 'integer',
            'genre' => 'integer',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'qty' => 'decimal:2',
            'jubelio_item_id' => 'integer',
        ];
    }

    protected $appends = ['image_url', 'item_code'];

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'items-list',
            'create' => 'items-create',
            'edit' => 'items-edit',
            'delete' => 'items-delete',

            // Asset Lancar
            'asset_lancar_view' => 'asset-lancar-list',
            'asset_lancar_create' => 'asset-lancar-create',
            'asset_lancar_edit' => 'asset-lancar-edit',
            'asset_lancar_delete' => 'asset-lancar-delete',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ItemGroup::class, 'group_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'item_tag');
    }

    public function warehouseItems(): HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    // Scopes
    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "{$term}%") // Optimised: prefix search uses index
                ->orWhere('pcode', 'like', "{$term}%")
                ->orWhere('id', $term);
        });
    }

    public function scopeFilterByTags(Builder $query, array $tagIds): void
    {
        if (empty($tagIds)) {
            return;
        }

        // Optimized "Match All" strategy:
        // Filter items that have at least one of the tags,
        // then group by item and ensure the count of matching tags equals the count of requested tags.
        // This is generally more performant than multiple EXISTS subqueries for large datasets.

        $query->whereHas('tags', function ($q) use ($tagIds) {
            $q->whereIn('tags.id', $tagIds);
        }, '=', count($tagIds));
    }

    // Helper Accessors (from legacy logic)
    public function getImageUrlAttribute(): string
    {
        $folderId = ($this->group_id > 0) ? $this->group_id : $this->id;
        $folder = str_pad(substr((string) $folderId, -2), 2, '0', STR_PAD_LEFT);
        $filename = $folderId.'.jpg';
        $path = config('core-nation.item_image_path').$folder.'/'.$filename;

        if (file_exists($path)) {
            return config('core-nation.item_image_url').$folder.'/'.$filename;
        }

        return asset('images/default-item.png');
    }

    public function getImagePathAttribute(): string
    {
        $folderId = ($this->group_id > 0) ? $this->group_id : $this->id;
        $folder = str_pad(substr((string) $folderId, -2), 2, '0', STR_PAD_LEFT);

        return config('core-nation.item_image_path').$folder.'/'.$folderId.'.jpg';
    }

    public function getItemCode(): string
    {
        if ($this->type === ItemType::ASSET_LANCAR) {
            return $this->code ?? (string) $this->id;
        }

        return $this->name ?? (string) $this->id;
    }

    public function getItemCodeAttribute(): string
    {
        return $this->getItemCode();
    }
}
