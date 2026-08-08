<?php

namespace App\Models;

use App\Enums\ItemType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemIdentityConversionRun extends Model
{
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'item_type',
        'dry_run',
        'batch_size',
        'processed_count',
        'success_count',
        'failed_count',
        'skipped_count',
        'user_id',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => ItemType::class,
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ItemIdentityConversionResult::class, 'run_id');
    }
}
