<?php

namespace App\Models;

use App\Support\FillsProductionColumnDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookDaily extends Model
{
    use HasFactory, FillsProductionColumnDefaults;

    protected $table = 'customer_class';

    public $timestamps = false;

    protected $guarded = ['id'];

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
