<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemInsightMonth extends Model
{
    protected $fillable = [
        'year',
        'month',
        'row_count',
        'calculated_by',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'row_count' => 'integer',
            'calculated_by' => 'integer',
            'calculated_at' => 'datetime',
        ];
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }
}
