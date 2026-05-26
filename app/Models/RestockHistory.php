<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockHistory extends Model
{
    protected $guarded = ['id'];

    public function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function restock()
    {
        return $this->belongsTo(Restock::class);
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
