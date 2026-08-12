<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory, FillsProductionColumnDefaults;

    protected $fillable = ['group', 'name', 'slug', 'value', 'location_id'];

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
        ];
    }

    public static function getValue(string $slug, mixed $default = null): mixed
    {
        $query = self::query();

        if (\Illuminate\Support\Facades\Schema::hasColumn((new static)->getTable(), 'slug')) {
            $query->where('slug', $slug);
        } else {
            $query->where('name', $slug);
        }

        $setting = $query->first();

        return $setting ? $setting->value : $default;
    }
}
