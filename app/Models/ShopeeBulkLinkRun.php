<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopeeBulkLinkRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'filename',
        'overwrite_existing',
        'status',
        'total_rows',
        'processed_rows',
        'linked_count',
        'skipped_count',
        'error_count',
        'last_batch_at',
        'payload',
        'recent_results',
        'completed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'overwrite_existing' => 'boolean',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'linked_count' => 'integer',
            'skipped_count' => 'integer',
            'error_count' => 'integer',
            'last_batch_at' => 'datetime',
            'completed_at' => 'datetime',
            'payload' => 'array',
            'recent_results' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function progressPercent(): float
    {
        if ($this->total_rows <= 0) {
            return 0.0;
        }

        return min(100.0, round(100 * $this->processed_rows / $this->total_rows, 1));
    }
}
