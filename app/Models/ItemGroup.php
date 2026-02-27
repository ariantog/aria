<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'description2',
        'alias',
        'master',
        'variant',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'group_id');
    }
}
