<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRetentionRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COPYING = 'copying';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_CLEANING = 'cleaning';

    public const STATUS_CLEANED = 'cleaned';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'transactions_copied' => 'integer',
            'details_copied' => 'integer',
            'customers_copied' => 'integer',
            'items_copied' => 'integer',
            'items_purged' => 'integer',
            'archive_started_at' => 'datetime',
            'archive_finished_at' => 'datetime',
            'cleanup_started_at' => 'datetime',
            'cleanup_finished_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'archive-view' => 'archive-view',
            'manage' => 'data-retention-manage',
        ];
    }

    public function isArchived(): bool
    {
        return in_array($this->status, [self::STATUS_ARCHIVED, self::STATUS_CLEANING, self::STATUS_CLEANED], true);
    }

    public function isCleaned(): bool
    {
        return $this->status === self::STATUS_CLEANED;
    }
}
