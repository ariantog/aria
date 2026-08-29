<?php

namespace App\Services\Reporting;

use App\Models\Addrbook;
use Illuminate\Support\Collection;

class ReportingContactFilter
{
    public function include(object $row): bool
    {
        if (! empty($row->is_internal_lending)) {
            return false;
        }

        return $row->is_active_in_reports !== false;
    }

    /**
     * Negative customer/reseller balances = they owe us (piutang usaha).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function receivables(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (object $row) => in_array((int) $row->customer_type, [Addrbook::TYPE_CUSTOMER, Addrbook::TYPE_RESELLER], true))
            ->filter(fn (object $row) => (float) $row->balance < 0)
            ->values();
    }

    /**
     * Positive supplier balances = we owe them (hutang usaha).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    public function payables(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (object $row) => (int) $row->customer_type === Addrbook::TYPE_SUPPLIER)
            ->filter(fn (object $row) => (float) $row->balance > 0)
            ->values();
    }
}
