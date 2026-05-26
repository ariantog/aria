<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $guarded = ['id'];

    public static function getPermissions(): array
    {
        return [
            'view' => 'restock-list',
            'create' => 'restock-create',
            'edit' => 'restock-edit',
            'delete' => 'restock-delete',
            'history' => 'restock-history',
        ];
    }

    public function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function group()
    {
        return $this->belongsTo(ItemGroup::class);
    }

    public function color()
    {
        return $this->belongsTo(Tag::class, 'color_id');
    }

    public function size()
    {
        return $this->belongsTo(Tag::class, 'size_id');
    }

    public function histories()
    {
        return $this->hasMany(RestockHistory::class);
    }
}
