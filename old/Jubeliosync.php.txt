<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jubeliosync extends Model
{
    use HasFactory;

    public function warehouse()
    {
        return $this->hasOne(Customer::class,'id','warehouse_id');
    }

    public function customer()
    {
        return $this->hasOne(Customer::class,'id','customer_id');
    }
}
