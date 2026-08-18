<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseArrangementRefreshJob extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const PHASE_STATS = 'stats';

    public const PHASE_SYNC = 'sync';

    protected $fillable = [
        'destination_warehouse_id',
        'user_id',
        'status',
        'phase',
        'item_cursor',
        'total_items',
        'stats_rows_inserted',
        'sync_candidates',
        'sync_sources',
        'error_message',
        'result_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'item_cursor' => 'integer',
            'total_items' => 'integer',
            'stats_rows_inserted' => 'integer',
            'sync_candidates' => 'integer',
            'sync_sources' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Addrbook::class, 'destination_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_PROCESSING], true);
    }

    public function progressPercent(): int
    {
        if ($this->phase === self::PHASE_SYNC) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 95;
        }

        if ($this->total_items <= 0) {
            return $this->item_cursor > 0 ? 100 : 0;
        }

        return min(94, (int) floor($this->item_cursor / $this->total_items * 94));
    }

    public function initiatedByLabel(): string
    {
        if ($this->user_id === null) {
            return 'System';
        }

        return $this->user?->name ?? 'User #'.$this->user_id;
    }
}
