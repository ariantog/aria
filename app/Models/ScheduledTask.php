<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ScheduledTask extends Model
{
    protected $fillable = [
        'name',
        'command',
        'frequency',
        'active',
        'description',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'last_run_at' => 'datetime',
        ];
    }

    public function getActiveAttribute(): bool
    {
        if (array_key_exists('active', $this->attributes)) {
            return (bool) $this->attributes['active'];
        }

        return (bool) ($this->attributes['is_active'] ?? false);
    }

    public function setActiveAttribute(bool $value): void
    {
        $this->attributes[self::activeColumn()] = $value;
    }

    public static function activeColumn(): string
    {
        if (Schema::hasColumn('scheduled_tasks', 'active')) {
            return 'active';
        }

        if (Schema::hasColumn('scheduled_tasks', 'is_active')) {
            return 'is_active';
        }

        return 'active';
    }

    /**
     * @return Builder<ScheduledTask>
     */
    public static function activeTasksQuery(): Builder
    {
        return static::query()->where(self::activeColumn(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'setting-cron-manager-view',
            'edit' => 'setting-cron-manager-edit',
        ];
    }
}
