<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AddrbookDaily extends Model
{
    use HasFactory;

    protected $table = 'addrbook_dailies';

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
        return $this->belongsTo(Addrbook::class);
    }

    /**
     * Define permissions associated with this model.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view_nett_cash' => 'reports-nett-cash',
            'view_cash_flow' => 'reports-cash-flow',
            'view_compare' => 'reports-compare',
            'view_inventory_health' => 'reports-inventory-health',
        ];
    }
}
