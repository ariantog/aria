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

    public function customers()
    {
        return $this->belongsToMany(Addrbook::class, 'location_customer', 'location_id', 'customer_id');
    }

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view' => 'users-locations-list',
            'create' => 'users-locations-create',
            'edit' => 'users-locations-edit',
            'delete' => 'users-locations-delete',
        ];
    }
}
