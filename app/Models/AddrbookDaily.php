<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AddrbookDaily extends Model
{
    use HasFactory;

    protected $table = 'customer_class';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (AddrbookDaily $daily): void {
            $table = $daily->getTable();

            if (Schema::hasColumn($table, 'class') && $daily->class === null) {
                $daily->class = '';
            }

            foreach (['adjust', 'depreciation', 'rating', 'cash_in', 'cash_out', 'sell', 'buy', 'return', 'return_supplier', 'use', 'move', 'transfer'] as $column) {
                if (Schema::hasColumn($table, $column) && $daily->{$column} === null) {
                    $daily->{$column} = 0;
                }
            }
        });
    }

    protected $casts = [
        'date' => 'date:Y-m-d',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'sell' => 'decimal:2',
        'buy' => 'decimal:2',
        'return' => 'decimal:2',
        'return_supplier' => 'decimal:2',
        'use' => 'decimal:2',
        'move' => 'decimal:2',
        'transfer' => 'decimal:2',
        'adjust' => 'decimal:2',
        'depreciation' => 'decimal:2',
    ];

    public function addrbook()
    {
        return $this->belongsTo(Addrbook::class, 'customer_id');
    }
}
