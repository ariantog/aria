<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['name', 'slug', 'value'];

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'setting-system-list',
            'create' => 'setting-system-create',
            'edit' => 'setting-system-edit',
            'delete' => 'setting-system-delete',
        ];
    }

    public static function getValue(string $slug, mixed $default = null): mixed
    {
        $setting = self::where('slug', $slug)->first();

        return $setting ? $setting->value : $default;
    }

    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory;
}
