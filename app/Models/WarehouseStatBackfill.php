<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseStatBackfill extends Model
{
    public const STATUS_IDLE = 'idle';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cursor_period' => 'integer',
            'oldest_period' => 'integer',
            'newest_period' => 'integer',
            'months_total' => 'integer',
            'months_done' => 'integer',
            'rows_written' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function progressPercent(): float
    {
        if ($this->months_total <= 0) {
            return 0.0;
        }

        return round(min(100, $this->months_done / $this->months_total * 100), 1);
    }
}
