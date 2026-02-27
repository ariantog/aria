<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $guarded = ['id'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'locations-list',
            'create' => 'locations-create',
            'edit' => 'locations-edit',
            'delete' => 'locations-delete',
        ];
    }
}
