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
            'cogs' => 'decimal:2',
            'adjustment' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }
}
