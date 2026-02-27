<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AddrbookType extends Model
{
    protected $guarded = ['id'];

    public function addrbooks()
    {
        return $this->hasMany(Addrbook::class);
    }
}
