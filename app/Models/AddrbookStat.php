<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookStat extends Model
{
    use HasFactory;

    protected $table = 'customerstat';

    protected $primaryKey = 'customer_id';

    public $incrementing = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (AddrbookStat $stat): void {
            if ($stat->balance === null) {
                $stat->balance = 0;
            }
        });
    }

    public function addrbook()
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
