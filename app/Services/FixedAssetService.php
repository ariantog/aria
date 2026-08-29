<?php

namespace App\Services;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\Addrbook;
use App\Models\Depreciation;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FixedAssetService
{
    public const SETTING_EXPENSE_ACCOUNT = 'asset_tetap.depreciation_expense_account_id';

    public const SETTING_CONTRA_ACCOUNT = 'asset_tetap.depreciation_contra_account_id';

    /**
     * @param  array{
     *     name: string,
     *     code?: string|null,
     *     description?: string|null,
     *     useful_life_months: int,
     *     residual_value?: float|int|string,
     *     warehouse_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function createRegister(array $data): Item
    {
        return DB::transaction(function () use ($data) {
            $code = strtoupper(trim((string) ($data['code'] ?? '')));
            $life = max(1, (int) $data['useful_life_months']);
            $residual = round((float) ($data['residual_value'] ?? 0), 2);
            $groupId = Schema::getConnection()->getDriverName() === 'mysql' ? 0 : null;

            $item = Item::create([
                'name' => trim($data['name']),
                'code' => $code !== '' ? $code : 'AT-TMP',
                'pcode' => $code !== '' ? $code : 'AT-TMP',
                'type' => ItemType::ASSET_TETAP,
                'brand' => ItemBrand::NO_BRAND,
                'group_id' => $groupId,
                'price' => 0,
                'cost' => 0,
                'qty' => 0,
                'description' => trim((string) ($data['description'] ?? '')),
            ]);

            if ($code === '') {
                $generated = 'AT-'.$item->id;
                $item->update([
                    'code' => $generated,
                    'pcode' => $generated,
                ]);
            }

            $placeholderDate = Carbon::today()->toDateString();

            Depreciation::query()->create([
                'item_id' => $item->id,
                'value' => $life,
                'buy_date' => $placeholderDate,
                'buy_price' => 0,
                'expire_date' => $placeholderDate,
                'residual_value' => $residual,
                'useful_life_months' => $life,
                'warehouse_id' => (int) ($data['warehouse_id'] ?? 0),
                'buy_transaction_id' => null,
                'notes' => trim((string) ($data['notes'] ?? '')),
            ]);

            return $item->fresh('depreciation');
        });
    }

    /**
     * @param  array{
     *     name?: string,
     *     code?: string|null,
     *     description?: string|null,
     *     useful_life_months?: int,
     *     residual_value?: float|int|string,
     *     warehouse_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function updateRegister(Item $item, array $data): Item
    {
        $this->assertAssetTetap($item);

        return DB::transaction(function () use ($item, $data) {
            $payload = [];
            if (array_key_exists('name', $data)) {
                $payload['name'] = trim((string) $data['name']);
            }
            if (array_key_exists('description', $data)) {
                $payload['description'] = trim((string) $data['description']);
            }
            if (array_key_exists('code', $data)) {
                $code = strtoupper(trim((string) $data['code']));
                if ($code !== '') {
                    $payload['code'] = $code;
                    $payload['pcode'] = $code;
                }
            }
            if ($payload !== []) {
                $item->update($payload);
            }

            $row = $item->depreciation;
            if (! $row) {
                throw ValidationException::withMessages([
                    'item' => ['Asset tetap register row is missing.'],
                ]);
            }

            if (array_key_exists('useful_life_months', $data)) {
                $life = max(1, (int) $data['useful_life_months']);
                $row->useful_life_months = $life;
                $row->value = $life;
            }
            if (array_key_exists('residual_value', $data)) {
                $row->residual_value = round((float) $data['residual_value'], 2);
            }
            if (array_key_exists('warehouse_id', $data)) {
                $row->warehouse_id = (int) ($data['warehouse_id'] ?? 0);
            }
            if (array_key_exists('notes', $data)) {
                $row->notes = trim((string) $data['notes']);
            }

            if ($row->hasBuyTransaction() && (float) $row->buy_price > 0) {
                $row->expire_date = $this->expireDate(
                    Carbon::parse($row->buy_date),
                    $this->resolveUsefulLifeMonths($row)
                );
            }

            $row->save();

            return $item->fresh('depreciation');
        });
    }

    public function resolveUsefulLifeMonths(Depreciation $row): int
    {
        if ((int) $row->useful_life_months > 0) {
            return (int) $row->useful_life_months;
        }

        if ($row->buy_date && $row->expire_date && $row->expire_date->gte($row->buy_date)) {
            $start = $row->buy_date->copy()->startOfMonth();
            $end = $row->expire_date->copy()->startOfMonth();

            return max(1, (($end->year - $start->year) * 12) + ($end->month - $start->month) + 1);
        }

        $value = (int) $row->value;
        if ($value <= 0) {
            return 0;
        }

        return $value <= 50 ? $value * 12 : $value;
    }

    public function expireDate(Carbon $buyDate, int $months): Carbon
    {
        $months = max(1, $months);

        return $buyDate->copy()->startOfMonth()->addMonthsNoOverflow($months - 1)->endOfMonth()->startOfDay();
    }

    public function monthlyAmount(Depreciation $row): float
    {
        $life = $this->resolveUsefulLifeMonths($row);
        if ($life <= 0) {
            return 0.0;
        }

        $depreciable = max(0.0, (float) $row->buy_price - (float) $row->residual_value);

        return round($depreciable / $life, 2);
    }

    public function accumulatedDepreciation(int $itemId, ?Carbon $asOf = null): float
    {
        return (float) TransactionDetail::query()
            ->where('item_id', $itemId)
            ->where('transaction_type', Transaction::TYPE_DEPRECIATION)
            ->when($asOf, fn ($query) => $query->whereDate('date', '<=', $asOf->toDateString()))
            ->sum('total');
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, float>
     */
    public function accumulatedByItemIds(array $itemIds, ?Carbon $asOf = null): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = TransactionDetail::query()
            ->whereIn('item_id', $itemIds)
            ->where('transaction_type', Transaction::TYPE_DEPRECIATION)
            ->when($asOf, fn ($query) => $query->whereDate('date', '<=', $asOf->toDateString()))
            ->selectRaw('item_id, SUM(total) as accumulated')
            ->groupBy('item_id')
            ->pluck('accumulated', 'item_id');

        $result = [];
        foreach ($itemIds as $id) {
            $result[(int) $id] = (float) ($rows[$id] ?? 0);
        }

        return $result;
    }

    public function netBookValue(Depreciation $row, ?Carbon $asOf = null, ?float $accumulated = null): float
    {
        $cost = (float) $row->buy_price;
        $residual = (float) $row->residual_value;
        $accum = $accumulated ?? $this->accumulatedDepreciation((int) $row->item_id, $asOf);

        return round(max($residual, $cost - $accum), 2);
    }

    public function hasPostedForMonth(int $itemId, Carbon $month): bool
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return TransactionDetail::query()
            ->where('item_id', $itemId)
            ->where('transaction_type', Transaction::TYPE_DEPRECIATION)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->exists();
    }

    /**
     * @return array{amount: float, remaining: float, monthly: float}|null
     */
    public function chargeForMonth(Depreciation $row, Carbon $month): ?array
    {
        if (! $row->hasBuyTransaction() || (float) $row->buy_price <= 0) {
            return null;
        }

        $life = $this->resolveUsefulLifeMonths($row);
        if ($life <= 0) {
            return null;
        }

        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $buyDate = Carbon::parse($row->buy_date)->startOfDay();
        $expireDate = Carbon::parse($row->expire_date ?: $this->expireDate($buyDate, $life))->startOfDay();

        if ($buyDate->greaterThan($monthEnd) || $expireDate->lessThan($monthStart)) {
            return null;
        }

        if ($this->hasPostedForMonth((int) $row->item_id, $month)) {
            return null;
        }

        $priorAsOf = $monthStart->copy()->subDay();
        $priorAccum = $this->accumulatedDepreciation((int) $row->item_id, $priorAsOf);
        $residual = (float) $row->residual_value;
        $remaining = round(max(0.0, (float) $row->buy_price - $priorAccum - $residual), 2);
        if ($remaining < 0.01) {
            return null;
        }

        $monthly = $this->monthlyAmount($row);
        $amount = round(min($monthly, $remaining), 2);
        if ($amount < 0.01) {
            return null;
        }

        return [
            'amount' => $amount,
            'remaining' => $remaining,
            'monthly' => $monthly,
        ];
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return Collection<int, array{item: Item, register: Depreciation, accumulated: float, nbv: float, monthly: float}>
     */
    public function presentRegisterRows(Collection $items, ?Carbon $asOf = null): Collection
    {
        $accum = $this->accumulatedByItemIds($items->pluck('id')->all(), $asOf);

        return $items->map(function (Item $item) use ($accum, $asOf) {
            $row = $item->depreciation;
            $accumulated = $accum[$item->id] ?? 0.0;

            return [
                'item' => $item,
                'register' => $row,
                'accumulated' => $accumulated,
                'nbv' => $row ? $this->netBookValue($row, $asOf, $accumulated) : 0.0,
                'monthly' => $row ? $this->monthlyAmount($row) : 0.0,
            ];
        });
    }

    public function assertAssetTetap(Item $item): void
    {
        if ($item->type !== ItemType::ASSET_TETAP) {
            throw ValidationException::withMessages([
                'item' => ['Item is not an asset tetap.'],
            ]);
        }
    }

    public function assertWarehouse(?int $warehouseId): ?Addrbook
    {
        if (! $warehouseId) {
            return null;
        }

        $warehouse = Addrbook::query()->find($warehouseId);
        if (! $warehouse || (int) $warehouse->type !== Addrbook::TYPE_WAREHOUSE) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Warehouse is invalid.'],
            ]);
        }

        return $warehouse;
    }

    public function assertAccount(int $accountId, string $field): Addrbook
    {
        $account = Addrbook::query()->find($accountId);
        if (! $account || (int) $account->type !== Addrbook::TYPE_ACCOUNT) {
            throw ValidationException::withMessages([
                $field => ['Journal account is invalid.'],
            ]);
        }

        return $account;
    }
}
