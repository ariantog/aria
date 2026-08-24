<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingOperationMonthlySummary extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cash_out' => 'decimal:2',
        ];
    }
}
