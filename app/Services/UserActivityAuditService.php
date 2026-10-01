<?php

namespace App\Services;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserActivityAuditService
{
    /**
     * @return array{
     *     month: string,
     *     from: string,
     *     to: string,
     *     late_entry_days: int,
     *     frequent_min_count: int,
     *     exclude_jubelio: bool,
     *     user_id: int|null,
     *     min_allowed_date: string
     * }
     */
    public function resolveFilters(array $input): array
    {
        $monthStart = $this->resolveAuditMonth($input['month'] ?? null);
        $monthEnd = $monthStart->copy()->endOfMonth();

        return [
            'month' => $monthStart->format('Y-m'),
            'from' => $monthStart->toDateString(),
            'to' => $monthEnd->toDateString(),
            'late_entry_days' => max(1, (int) ($input['late_entry_days'] ?? config('user_activity_audit.late_entry_days', 7))),
            'frequent_min_count' => max(1, (int) ($input['frequent_min_count'] ?? config('user_activity_audit.frequent_min_count', 3))),
            'exclude_jubelio' => array_key_exists('exclude_jubelio', $input)
                ? filter_var($input['exclude_jubelio'], FILTER_VALIDATE_BOOLEAN)
                : (bool) config('user_activity_audit.exclude_jubelio_by_default', true),
            'user_id' => isset($input['user_id']) && $input['user_id'] !== '' && $input['user_id'] !== null
                ? (int) $input['user_id']
                : null,
            'min_allowed_date' => $this->minAllowedDateForAuditMonth($monthStart)->toDateString(),
        ];
    }

    public function resolveAuditMonth(?string $month): Carbon
    {
        if ($month !== null && $month !== '') {
            $parsed = Carbon::createFromFormat('Y-m', (string) $month);

            if ($parsed !== false) {
                return $parsed->startOfMonth()->startOfDay();
            }
        }

        return Carbon::today()->startOfMonth()->startOfDay();
    }

    /**
     * Earliest allowed transaction date for entries in the audited month (previous month start, same rule as tutup buku).
     */
    public function minAllowedDateForAuditMonth(Carbon $monthStart): Carbon
    {
        return $monthStart->copy()->endOfMonth()->subMonthNoOverflow()->startOfMonth()->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Transaction>
     */
    public function baseManualQuery(array $filters): Builder
    {
        $query = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('date', '>=', $filters['from'])
            ->whereDate('date', '<=', $filters['to']);

        if ($filters['exclude_jubelio']) {
            $query->where('submit_type', Transaction::SUBMIT_TYPE_MANUAL)
                ->where('user_id', '!=', Transaction::JUBELIO_CRON_USER_ID);
        }

        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        return $query;
    }

    /**
     * Transactions dated before the current book-closing window and/or entered long after their date.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Transaction>
     */
    public function suspiciousTimingQuery(array $filters): Builder
    {
        $minAllowed = $filters['min_allowed_date'];
        $lateDays = (int) $filters['late_entry_days'];

        return $this->baseManualQuery($filters)
            ->where(function (Builder $q) use ($minAllowed, $lateDays) {
                $q->whereDate('date', '<', $minAllowed)
                    ->orWhere(function (Builder $inner) use ($lateDays) {
                        $this->whereCreatedDaysAfterDate($inner, $lateDays);
                    });
            });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function paginateSuspiciousTiming(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->suspiciousTimingQuery($filters)
            ->with(['user', 'sender', 'receiver'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{user_id: int, username: string, name: string, move_count: int}>
     */
    public function frequentVirtualWarehouseMoves(array $filters): array
    {
        $minCount = (int) $filters['frequent_min_count'];

        $rows = $this->baseManualQuery($filters)
            ->where('type', Transaction::TYPE_MOVE)
            ->where('receiver_type', (string) Addrbook::TYPE_V_WAREHOUSE)
            ->select('user_id', DB::raw('COUNT(*) as move_count'))
            ->groupBy('user_id')
            ->having('move_count', '>=', $minCount)
            ->orderByDesc('move_count')
            ->limit(50)
            ->get();

        return $this->hydrateUserLeaderboard($rows, 'move_count');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{user_id: int, username: string, name: string, sell_count: int, zero_total_count: int, discounted_count: int}>
     */
    public function frequentDiscountedOrZeroSells(array $filters): array
    {
        $minCount = (int) $filters['frequent_min_count'];

        $rows = $this->baseManualQuery($filters)
            ->where('type', Transaction::TYPE_SELL)
            ->where(function (Builder $q) {
                $q->where('discount', '>', 0)
                    ->orWhere('total', '=', 0);
            })
            ->select(
                'user_id',
                DB::raw('COUNT(*) as sell_count'),
                DB::raw('SUM(CASE WHEN total = 0 THEN 1 ELSE 0 END) as zero_total_count'),
                DB::raw('SUM(CASE WHEN discount > 0 THEN 1 ELSE 0 END) as discounted_count'),
            )
            ->groupBy('user_id')
            ->having('sell_count', '>=', $minCount)
            ->orderByDesc('sell_count')
            ->limit(50)
            ->get();

        $out = [];
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        foreach ($rows as $row) {
            $user = $users->get((int) $row->user_id);
            $out[] = [
                'user_id' => (int) $row->user_id,
                'username' => $user?->username ?? '#'.$row->user_id,
                'name' => $user?->name ?? '—',
                'sell_count' => (int) $row->sell_count,
                'zero_total_count' => (int) $row->zero_total_count,
                'discounted_count' => (int) $row->discounted_count,
            ];
        }

        return $out;
    }

    public function timingFlags(Transaction $transaction, array $filters): array
    {
        $flags = [];
        $minAllowed = Carbon::parse($filters['min_allowed_date'])->startOfDay();
        $txnDate = $transaction->date instanceof Carbon
            ? $transaction->date->copy()->startOfDay()
            : Carbon::parse($transaction->date)->startOfDay();

        if ($txnDate->lessThan($minAllowed)) {
            $flags[] = 'Tanggal sebelum jendela tutup buku';
        }

        $created = $transaction->created_at instanceof Carbon
            ? $transaction->created_at->copy()->startOfDay()
            : Carbon::parse($transaction->created_at)->startOfDay();
        $lateDays = (int) $filters['late_entry_days'];
        if ($created->greaterThanOrEqualTo($txnDate) && $txnDate->diffInDays($created) >= $lateDays) {
            $flags[] = 'Entri '.$lateDays.'+ hari setelah tanggal transaksi';
        }

        return $flags;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array{user_id: int, username: string, name: string, move_count: int}>
     */
    protected function hydrateUserLeaderboard(Collection $rows, string $countKey): array
    {
        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');
        $out = [];

        foreach ($rows as $row) {
            $user = $users->get((int) $row->user_id);
            $out[] = [
                'user_id' => (int) $row->user_id,
                'username' => $user?->username ?? '#'.$row->user_id,
                'name' => $user?->name ?? '—',
                $countKey => (int) $row->{$countKey},
            ];
        }

        return $out;
    }

    protected function whereCreatedDaysAfterDate(Builder $query, int $days): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereRaw('CAST(julianday(date(created_at)) - julianday(date) AS INTEGER) >= ?', [$days]);

            return;
        }

        $query->whereRaw('DATEDIFF(DATE(created_at), date) >= ?', [$days]);
    }
}
