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
            'view-export-sell' => 'report-export-sell',
            'view-expense' => 'report-expense',
            'view-asset-tetap' => 'report-asset-tetap',
            'view-warehouse-item' => 'report-warehouse-item',
            'view-warehouse-arrangement' => 'report-warehouse-arrangement',
            'view-product-performance' => 'report-product-performance',
            'view-produksi-potong' => 'report-produksi-potong',
            'view-produksi-qc' => 'report-produksi-qc',
            'view-tax-ppn' => 'report-tax-ppn',
            'view-tax-pph' => 'report-tax-pph',
            'view-tax-faktur' => 'report-tax-faktur',
            'import-tax-faktur' => 'report-tax-faktur-import',
            'view-neraca' => 'report-neraca',
            'view-laba-rugi' => 'report-laba-rugi',
            'view-receivables' => 'report-receivables',
            'view-payables' => 'report-payables',
        ];
    }
}
