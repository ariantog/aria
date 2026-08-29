<?php

namespace App\Services\Reporting;

use App\Enums\ItemType;
use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingMonthlyInventoryValue;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryRollForwardService
{
    public function startDate(): Carbon
    {
        return Carbon::parse((string) config('reporting.persediaan_start', '2026-01-01'))->startOfDay();
    }

    public function openingSeed(): float
    {
        $raw = Setting::getValue('reporting.persediaan_awal', config('reporting.persediaan_awal', 0));

        return (float) $raw;
    }

    public function isBeforeStart(int $year, int $month): bool
    {
        return ReportingPeriod::monthEnd($year, $month)->lt($this->startDate());
    }

    /**
     * Recompute and persist every month from the persediaan start through the target month.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ensureThrough(int $year, int $month): Collection
    {
        $rows = collect();
        if ($this->isBeforeStart($year, $month)) {
            return $rows;
        }

        $cursor = $this->startDate()->copy();
        $target = ReportingPeriod::monthStart($year, $month);

        while ($cursor->lte($target)) {
            $rows->push($this->forMonth($cursor->year, $cursor->month));
            $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     opening: float,
     *     material_purchases: float,
     *     material_cash_out: float,
     *     production_cost: float,
     *     cogs: float,
     *     adjustment: float,
     *     closing: float,
     *     production_cost_by_entity: array<int, float>,
     *     material_cash_out_by_entity: array<int, float>,
     * }
     */
    public function forMonth(int $year, int $month): array
    {
        if ($this->isBeforeStart($year, $month)) {
            return $this->emptyMonth($year, $month);
        }

        $existing = ReportingMonthlyInventoryValue::query()
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $flows = $this->flowForMonth($year, $month);
        $opening = $this->openingFor($year, $month);
        $adjustment = $existing ? (float) $existing->adjustment : 0.0;
        $closing = $this->closingFrom($opening, $flows, $adjustment);

        $row = ReportingMonthlyInventoryValue::query()->updateOrCreate(
            ['year' => $year, 'month' => $month],
            [
                'opening_balance' => $opening,
                'material_purchases' => $flows['material_purchases'],
                'material_cash_out' => $flows['material_cash_out'],
                'production_cost' => $flows['production_cost'],
                'cogs' => $flows['cogs'],
                'adjustment' => $adjustment,
                'closing_balance' => $closing,
            ],
        );

        return [
            'year' => $year,
            'month' => $month,
            'opening' => (float) $row->opening_balance,
            'material_purchases' => (float) $row->material_purchases,
            'material_cash_out' => (float) $row->material_cash_out,
            'production_cost' => (float) $row->production_cost,
            'cogs' => (float) $row->cogs,
            'adjustment' => (float) $row->adjustment,
            'closing' => (float) $row->closing_balance,
            'production_cost_by_entity' => $flows['production_cost_by_entity'],
            'material_cash_out_by_entity' => $flows['material_cash_out_by_entity'],
        ];
    }

    /**
     * @return array{
     *     material_purchases: float,
     *     material_cash_out: float,
     *     production_cost: float,
     *     cogs: float,
     *     production_cost_by_entity: array<int, float>,
     *     material_cash_out_by_entity: array<int, float>,
     * }
     */
    public function flowForMonth(int $year, int $month): array
    {
        [$start, $end] = ReportingPeriod::monthRange($year, $month);

        $materialIds = ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::Material);
        $productionIds = ReportingLedgerRoleModel::customerIdsFor(ReportingLedgerRole::ProductionCost);

        $purchases = $this->inventoryLineTotal(
            [Transaction::TYPE_BUY],
            [Transaction::TYPE_RETURN_SUPPLIER],
            $start,
            $end,
        );

        $cogs = $this->cogsTotal($start, $end);

        $materialCash = $this->cashOutToLedgers($materialIds, $start, $end);
        $productionCash = $this->cashOutToLedgers($productionIds, $start, $end);

        return [
            'material_purchases' => $purchases,
            'material_cash_out' => $materialCash['total'],
            'production_cost' => $productionCash['total'],
            'cogs' => $cogs,
            'production_cost_by_entity' => $productionCash['by_entity'],
            'material_cash_out_by_entity' => $materialCash['by_entity'],
        ];
    }

    /**
     * @param  array{material_purchases: float, material_cash_out: float, production_cost: float, cogs: float}  $flows
     */
    public function closingFrom(float $opening, array $flows, float $adjustment = 0.0): float
    {
        return $opening
            + $flows['material_purchases']
            - $flows['material_cash_out']
            - $flows['production_cost']
            - $flows['cogs']
            + $adjustment;
    }

    private function openingFor(int $year, int $month): float
    {
        $start = $this->startDate();
        if ($year === $start->year && $month === $start->month) {
            return $this->openingSeed();
        }

        [$prevYear, $prevMonth] = ReportingPeriod::previousMonth($year, $month);
        $previous = ReportingMonthlyInventoryValue::query()
            ->where('year', $prevYear)
            ->where('month', $prevMonth)
            ->first();

        if ($previous) {
            return (float) $previous->closing_balance;
        }

        return $this->forMonth($prevYear, $prevMonth)['closing'];
    }

    /**
     * Buy / return-supplier line totals for inventory item types (not fixed assets).
     *
     * @param  list<int>  $addTypes
     * @param  list<int>  $subtractTypes
     */
    private function inventoryLineTotal(array $addTypes, array $subtractTypes, string $start, string $end): float
    {
        $inventoryTypes = [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value];

        $sumFor = function (array $types) use ($inventoryTypes, $start, $end): float {
            if ($types === []) {
                return 0.0;
            }

            $row = DB::table('transaction_details as td')
                ->join('transactions as t', 't.id', '=', 'td.transaction_id')
                ->join('items as i', 'i.id', '=', 'td.item_id')
                ->where('t.status', Transaction::STATUS_COMPLETED)
                ->whereIn('t.type', $types)
                ->whereIn('i.type', $inventoryTypes)
                ->whereBetween('t.date', [$start, $end])
                ->selectRaw('COALESCE(SUM(ABS(td.total)), 0) as amount')
                ->first();

            return (float) $row->amount;
        };

        return $sumFor($addTypes) - $sumFor($subtractTypes);
    }

    private function cogsTotal(string $start, string $end): float
    {
        $inventoryTypes = [ItemType::ITEM->value, ItemType::ASSET_LANCAR->value];

        $sumFor = function (int $type) use ($inventoryTypes, $start, $end): float {
            $row = DB::table('transaction_details as td')
                ->join('transactions as t', 't.id', '=', 'td.transaction_id')
                ->join('items as i', 'i.id', '=', 'td.item_id')
                ->where('t.status', Transaction::STATUS_COMPLETED)
                ->where('t.type', $type)
                ->whereIn('i.type', $inventoryTypes)
                ->whereBetween('t.date', [$start, $end])
                ->selectRaw('COALESCE(SUM(td.quantity * COALESCE(i.cost, 0)), 0) as amount')
                ->first();

            return (float) $row->amount;
        };

        return $sumFor(Transaction::TYPE_SELL) - $sumFor(Transaction::TYPE_RETURN);
    }

    /**
     * @param  list<int>  $ledgerIds
     * @return array{total: float, by_entity: array<int, float>}
     */
    private function cashOutToLedgers(array $ledgerIds, string $start, string $end): array
    {
        if ($ledgerIds === []) {
            return ['total' => 0.0, 'by_entity' => []];
        }

        $rows = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->where('type', Transaction::TYPE_CASH_OUT)
            ->where('receiver_type', Addrbook::TYPE_ACCOUNT)
            ->whereIn('receiver_id', $ledgerIds)
            ->whereBetween('date', [$start, $end])
            ->get(['id', 'sender_id', 'sender_type', 'total']);

        $byEntity = [];
        $total = 0.0;

        foreach ($rows as $row) {
            $amount = abs((float) $row->total);
            $total += $amount;

            $entityId = 0;
            if ((int) $row->sender_type === Addrbook::TYPE_BANK && $row->sender_id) {
                $entityId = (int) (ReportingEntity::findActiveForBank((int) $row->sender_id)?->id ?? 0);
            }

            $byEntity[$entityId] = ($byEntity[$entityId] ?? 0) + $amount;
        }

        return ['total' => $total, 'by_entity' => $byEntity];
    }

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     opening: float,
     *     material_purchases: float,
     *     material_cash_out: float,
     *     production_cost: float,
     *     cogs: float,
     *     adjustment: float,
     *     closing: float,
     *     production_cost_by_entity: array<int, float>,
     *     material_cash_out_by_entity: array<int, float>,
     * }
     */
    private function emptyMonth(int $year, int $month): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'opening' => 0.0,
            'material_purchases' => 0.0,
            'material_cash_out' => 0.0,
            'production_cost' => 0.0,
            'cogs' => 0.0,
            'adjustment' => 0.0,
            'closing' => 0.0,
            'production_cost_by_entity' => [],
            'material_cash_out_by_entity' => [],
        ];
    }
}
