<?php

namespace App\Models;

class Report
{
    /**
     * Define permissions associated with reports.
     * This model is used only for permission management.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [
            'view-nett-cash' => 'report-nett-cash',
            'view-cash-flow' => 'report-cash-flow',
            'view-compare' => 'report-compare',
            'view-inventory-health' => 'report-inventory-health',
            'view-purchase' => 'report-purchase',
            'view-expense' => 'report-expense',
            'view-warehouse-item' => 'report-warehouse-item',
            'view-item-sales' => 'report-item-sales',
            'view-warehouse-arrangement' => 'report-warehouse-arrangement',
        ];
    }
}
