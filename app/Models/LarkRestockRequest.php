<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LarkRestockRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public static function createForItem(int $itemId, int $suggestedQty, array $context): self
    {
        return self::query()->create([
            'token' => (string) Str::uuid(),
            'item_id' => $itemId,
            'suggested_qty' => max(0, $suggestedQty),
            'context_snapshot' => $context,
            'status' => self::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
