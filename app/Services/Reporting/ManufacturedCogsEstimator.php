<?php

namespace App\Services\Reporting;

use App\Enums\ItemType;
use App\Models\Borongan;
use App\Models\Produksi;
use App\Models\ReportingMonthlyInventoryValue;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide HPP estimate for manufactured goods.
 *
 * Unit cost = (borongan labour + Material Produksi cash-out) / pcs that entered gudang.
 * Sold qty of items that appear on produksi uses that unit cost; other inventory
 * items still use `items.cost`.
 */
class ManufacturedCogsEstimator
{
    /**
     * @return array{
     *     pcs_manufactured: float,
     *     pcs_manufactured_ytd: float,
     *     pcs_manufactured_week: float,
     *     borongan_labor: float,
     *     borongan_labor_ytd: float,
     *     capitalized_labor: float,
     *     labor_source: 'borongan'|'gaji'|'none',
     *     material: float,
     *     unit_cost: float,
     *     unit_cost_source: 'month'|'prior'|'ytd'|'none',
     *     manufactured_qty_sold: float,
     *     purchased_qty_sold: float,
     *     manufactured_cogs: float,
     *     purchased_cogs: float,
     *     cogs: float,
     *     capitalize_conversion: bool,
     * }
     */
    public function estimate(int $year, int $month, float $materialCashOut, float $gajiMingguan): array
    {
        [$start, $end] = ReportingPeriod::monthRange($year, $month);
        $asOf = ReportingPeriod::asOf($year, $month);
        $weekStart = $asOf->copy()->subDays(6)->toDateString();
        $ytdStart = Carbon::create($year, 1, 1)->toDateString();

        $pcsMonth = $this->pcsEnteredGudang($start, $end);
        $pcsYtd = $this->pcsEnteredGudang($ytdStart, $end);
        $pcsWeek = $this->pcsEnteredGudang($weekStart, $asOf->toDateString());

        $boronganMonth = $this->boronganLabor($start, $end);
        $boronganYtd = $this->boronganLabor($ytdStart, $end);

        if ($boronganMonth > 0.0) {
            $capitalizedLabor = $boronganMonth;
            $laborSource = 'borongan';
        } elseif ($gajiMingguan > 0.0) {
            $capitalizedLabor = $gajiMingguan;
            $laborSource = 'gaji';
        } else {
            $capitalizedLabor = 0.0;
            $laborSource = 'none';
        }

        $inputs = $capitalizedLabor + $materialCashOut;
        [$unitCost, $unitCostSource] = $this->resolveUnitCost(
            $year,
            $month,
            $pcsMonth,
            $inputs,
        );

        $manufacturedIds = $this->manufacturedItemIds();
        $manufacturedQty = $this->signedSoldQty($start, $end, $manufacturedIds, manufactured: true);
        $purchasedQty = $this->signedSoldQty($start, $end, $manufacturedIds, manufactured: false);
        $purchasedCogs = $this->purchasedCogs($start, $end, $manufacturedIds);
        $manufacturedCogs = round($unitCost * $manufacturedQty, 2);

        $capitalizeConversion = $boronganMonth > 0.0
            || $pcsMonth > 0.0
            || abs($manufacturedQty) >= 0.0001;

        return [
            'pcs_manufactured' => $pcsMonth,
            'pcs_manufactured_ytd' => $pcsYtd,
            'pcs_manufactured_week' => $pcsWeek,
            'borongan_labor' => $boronganMonth,
            'borongan_labor_ytd' => $boronganYtd,
            'capitalized_labor' => $capitalizeConversion ? $capitalizedLabor : 0.0,
            'labor_source' => $laborSource,
            'material' => $materialCashOut,
            'unit_cost' => $unitCost,
            'unit_cost_source' => $unitCostSource,
            'manufactured_qty_sold' => $manufacturedQty,
            'purchased_qty_sold' => $purchasedQty,
            'manufactured_cogs' => $manufacturedCogs,
            'purchased_cogs' => $purchasedCogs,
            'cogs' => round($manufacturedCogs + $purchasedCogs, 2),
            'capitalize_conversion' => $capitalizeConversion,
        ];
    }

    /**
     * Finished goods that entered the warehouse in the date range (status gudang / both).
     */
    public function pcsEnteredGudang(string $start, string $end): float
    {
        if (! Schema::hasTable('prod_produksi')) {
            return 0.0;
        }

        $quantity = Produksi::query()
            ->whereIn('status', [Produksi::STATUS_GUDANG, Produksi::STATUS_BOTH])
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($inner) use ($start, $end) {
                    $inner->whereNotNull('gudang_date')
                        ->whereDate('gudang_date', '>=', $start)
                        ->whereDate('gudang_date', '<=', $end);
                })->orWhere(function ($inner) use ($start, $end) {
                    $inner->whereNull('gudang_date')
                        ->whereNotNull('setor_date')
                        ->whereDate('setor_date', '>=', $start)
                        ->whereDate('setor_date', '<=', $end);
                });
            })
            ->sum('quantity');

        return (float) $quantity;
    }

    /**
     * Borongan totals overlapping the range, pro-rated by calendar days.
     */
    public function boronganLabor(string $start, string $end): float
    {
        if (! Schema::hasTable('prod_borongan')) {
            return 0.0;
        }

        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();

        $rows = Borongan::query()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($inner) use ($start, $end) {
                    $inner->whereNotNull('from')
                        ->whereNotNull('to')
                        ->whereDate('from', '<=', $end)
                        ->whereDate('to', '>=', $start);
                })->orWhere(function ($inner) use ($start, $end) {
                    $inner->where(function ($missing) {
                        $missing->whereNull('from')->orWhereNull('to');
                    })->whereNotNull('date')
                        ->whereDate('date', '>=', $start)
                        ->whereDate('date', '<=', $end);
                });
            })
            ->get(['from', 'to', 'date', 'total']);

        $amount = 0.0;

        foreach ($rows as $row) {
            $amount += $this->allocateBoronganTotal($row, $startDate, $endDate);
        }

        return round($amount, 2);
    }

    /**
     * @return list<int>
     */
    public function manufacturedItemIds(): array
    {
        if (! Schema::hasTable('prod_produksi')) {
            return [];
        }

        return Produksi::query()
            ->where('item_id', '>', 0)
            ->distinct()
            ->pluck('item_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array{0: float, 1: 'month'|'prior'|'ytd'|'none'}
     */
    private function resolveUnitCost(int $year, int $month, float $pcsMonth, float $inputs): array
    {
        if ($pcsMonth > 0.0) {
            return [round($inputs / $pcsMonth, 4), 'month'];
        }

        if (! Schema::hasColumn('reporting_monthly_inventory_values', 'manufactured_unit_cost')) {
            return [0.0, 'none'];
        }

        [$prevYear, $prevMonth] = ReportingPeriod::previousMonth($year, $month);
        $prior = ReportingMonthlyInventoryValue::query()
            ->where('year', $prevYear)
            ->where('month', $prevMonth)
            ->first();

        if ($prior && (float) $prior->manufactured_unit_cost > 0.0) {
            return [(float) $prior->manufactured_unit_cost, 'prior'];
        }

        $earlier = ReportingMonthlyInventoryValue::query()
            ->where('year', $year)
            ->where('month', '<', $month)
            ->get(['pcs_manufactured', 'borongan_labor', 'material_cash_out', 'production_cost']);

        $ytdPcs = (float) $earlier->sum(fn (ReportingMonthlyInventoryValue $row) => (float) $row->pcs_manufactured);
        $ytdInputs = (float) $earlier->sum(function (ReportingMonthlyInventoryValue $row) {
            $labor = (float) $row->borongan_labor;

            return $labor + (float) $row->material_cash_out;
        });

        if ($ytdPcs > 0.0) {
            return [round($ytdInputs / $ytdPcs, 4), 'ytd'];
        }

        return [0.0, 'none'];
    }

    /**
     * @param  list<int>  $manufacturedIds
     */
    private function signedSoldQty(string $start, string $end, array $manufacturedIds, bool $manufactured): float
    {
        if ($manufactured && $manufacturedIds === []) {
            return 0.0;
        }

        $sell = $this->inventoryQty(Transaction::TYPE_SELL, $start, $end, $manufacturedIds, $manufactured);
        $return = $this->inventoryQty(Transaction::TYPE_RETURN, $start, $end, $manufacturedIds, $manufactured);

        return $sell - $return;
    }

    /**
     * @param  list<int>  $manufacturedIds
     */
    private function purchasedCogs(string $start, string $end, array $manufacturedIds): float
    {
        $sell = $this->inventoryCostAmount(Transaction::TYPE_SELL, $start, $end, $manufacturedIds);
        $return = $this->inventoryCostAmount(Transaction::TYPE_RETURN, $start, $end, $manufacturedIds);

        return $sell - $return;
    }

    /**
     * @param  list<int>  $manufacturedIds
     */
    private function inventoryQty(int $type, string $start, string $end, array $manufacturedIds, bool $manufactured): float
    {
        if ($manufactured && $manufacturedIds === []) {
            return 0.0;
        }

        $query = $this->inventoryDetailQuery($type, $start, $end);
        $this->scopeManufactured($query, $manufacturedIds, $manufactured);

        return (float) $query->sum('td.quantity');
    }

    /**
     * @param  list<int>  $manufacturedIds
     */
    private function inventoryCostAmount(int $type, string $start, string $end, array $manufacturedIds): float
    {
        $query = $this->inventoryDetailQuery($type, $start, $end);
        $this->scopeManufactured($query, $manufacturedIds, manufactured: false);

        $row = $query->selectRaw('COALESCE(SUM(td.quantity * COALESCE(i.cost, 0)), 0) as amount')->first();

        return (float) $row->amount;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function inventoryDetailQuery(int $type, string $start, string $end)
    {
        return DB::table('transaction_details as td')
            ->join('transactions as t', 't.id', '=', 'td.transaction_id')
            ->join('items as i', 'i.id', '=', 'td.item_id')
            ->where('t.status', Transaction::STATUS_COMPLETED)
            ->where('t.type', $type)
            ->whereIn('i.type', [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value])
            ->whereBetween('t.date', ReportingPeriod::queryBounds($start, $end));
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  list<int>  $manufacturedIds
     */
    private function scopeManufactured($query, array $manufacturedIds, bool $manufactured): void
    {
        if ($manufacturedIds === []) {
            return;
        }

        if ($manufactured) {
            $query->whereIn('td.item_id', $manufacturedIds);

            return;
        }

        $query->whereNotIn('td.item_id', $manufacturedIds);
    }

    private function allocateBoronganTotal(Borongan $row, Carbon $start, Carbon $end): float
    {
        $total = (float) $row->total;

        if (! $row->from || ! $row->to) {
            return $total;
        }

        $from = Carbon::parse($row->from)->startOfDay();
        $to = Carbon::parse($row->to)->startOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $overlapStart = $from->greaterThan($start) ? $from : $start;
        $overlapEnd = $to->lessThan($end) ? $to : $end;

        if ($overlapEnd->lt($overlapStart)) {
            return 0.0;
        }

        $overlapDays = $this->inclusiveDays($overlapStart, $overlapEnd);
        $spanDays = $this->inclusiveDays($from, $to);

        if ($overlapDays <= 0 || $spanDays <= 0) {
            return 0.0;
        }

        return $total * $overlapDays / $spanDays;
    }

    private function inclusiveDays(Carbon $from, Carbon $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $days = (int) $start->diffInDays($end, false);

        return $days < 0 ? 0 : $days + 1;
    }
}
