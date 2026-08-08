<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemIdentityConversionResult extends Model
{
    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'run_id',
        'item_id',
        'status',
        'failure_code',
        'detail',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ItemIdentityConversionRun::class, 'run_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public static function latestSuccessfulForItem(int $itemId): ?self
    {
        return static::query()
            ->where('item_id', $itemId)
            ->where('status', self::STATUS_SUCCESS)
            ->latest('id')
            ->first();
    }
}
