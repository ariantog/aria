<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingMonthlyInventoryValue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'material_purchases' => 'decimal:2',
            'material_cash_out' => 'decimal:2',
            'production_cost' => 'decimal:2',
            'pcs_manufactured' => 'decimal:2',
            'borongan_labor' => 'decimal:2',
            'manufactured_unit_cost' => 'decimal:4',
            'manufactured_qty_sold' => 'decimal:2',
            'manufactured_cogs' => 'decimal:2',
            'purchased_cogs' => 'decimal:2',
            'cogs' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }
}
