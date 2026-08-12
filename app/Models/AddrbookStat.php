<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookStat extends Model
{
    use HasFactory, FillsProductionColumnDefaults;

    protected $table = 'customerstat';

    protected $primaryKey = 'customer_id';

    public $incrementing = false;

    protected $guarded = [];

    public function addrbook()
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
