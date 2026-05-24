<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;

    protected $fillable = ['group', 'name', 'slug', 'value'];

    const DAY_MAP = [
        'Senin' => 'Monday',
        'Selasa' => 'Tuesday',
        'Rabu' => 'Wednesday',
        'Kamis' => 'Thursday',
        'Jumat' => 'Friday',
        'Sabtu' => 'Saturday',
        'Minggu' => 'Sunday',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'setting-general-view',
            'edit' => 'setting-general-edit',
            'create' => 'setting-general-create',
            'delete' => 'setting-general-delete',

            // Cron Manager
            'cron-view' => 'setting-cron-manager-view',
            'cron-edit' => 'setting-cron-manager-edit',
        ];
    }

    public static function getValue(string $slug, mixed $default = null): mixed
    {
        $setting = self::where('slug', $slug)->first();

        return $setting ? $setting->value : $default;
    }
}
