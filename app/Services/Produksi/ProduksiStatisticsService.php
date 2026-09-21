<?php

namespace App\Services\Produksi;

use App\Models\Produksi;
use App\Models\Worker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ProduksiStatisticsService
{
    /**
     * @return array{0: string, 1: string, 2: ?int, 3: int}
     */
    public function resolveDateRange(?int $month, ?int $year): array
    {
        $year = $year ?? (int) date('Y');

        if ($month && $month > 0) {
            $date = Carbon::createFromDate($year, $month, 1);

            return [
                $date->copy()->startOfMonth()->toDateString(),
                $date->copy()->endOfMonth()->toDateString(),
                $month,
                $year,
            ];
        }

        return [
            Carbon::createFromDate($year, 1, 1)->startOfYear()->toDateString(),
            Carbon::createFromDate($year, 12, 31)->endOfYear()->toDateString(),
            null,
            $year,
        ];
    }

    /**
     * @return list<int>
     */
    public function yearList(): array
    {
        return range((int) date('Y'), 2019);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     startDate: string,
     *     endDate: string,
     *     status: ?int,
     *     hasCustomRange: bool,
     *     periodLabel: string,
     *     filters: array{month: ?int, year: int, from: ?string, to: ?string, status: ?int}
     * }
     */
    public function reportContext(array $query): array
    {
        $monthRaw = $query['month'] ?? null;
        $month = ($monthRaw === null || $monthRaw === '' || $monthRaw === '0') ? null : (int) $monthRaw;
        $year = isset($query['year']) && $query['year'] !== '' && $query['year'] !== null
            ? (int) $query['year']
            : (int) date('Y');

        $from = $this->normalizeDateQuery(isset($query['from']) ? (string) $query['from'] : null);
        $to = $this->normalizeDateQuery(isset($query['to']) ? (string) $query['to'] : null);
        $status = Produksi::parseStatusFilter($query['status'] ?? null);
        $hasCustomRange = $from !== null || $to !== null;

        if ($hasCustomRange) {
            $startDate = $from ?? '1970-01-01';
            $endDate = $to ?? now()->toDateString();
            $resolvedMonth = $month;
            $resolvedYear = $year;
        } else {
            [$startDate, $endDate, $resolvedMonth, $resolvedYear] = $this->resolveDateRange($month, $year);
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'status' => $status,
            'hasCustomRange' => $hasCustomRange,
            'periodLabel' => $this->reportPeriodLabel($resolvedMonth, $resolvedYear, $from, $to, $status),
            'filters' => [
                'month' => $resolvedMonth,
                'year' => $resolvedYear,
                'from' => $from,
                'to' => $to,
                'status' => $status,
            ],
        ];
    }

    /**
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    public function potongMonthlyTotals(int $year, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('potong_id')
                ->whereNotNull('potong_date')
                ->whereYear('potong_date', $year),
            $status
        )->get(['potong_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'potong_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, sjp_count: int, kode_count: int, avg_qty: float}>
     */
    public function potongWorkerSummary(string $startDate, string $endDate, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('potong_id')
                ->whereDate('potong_date', '>=', $startDate)
                ->whereDate('potong_date', '<=', $endDate),
            $status
        )->get(['potong_id', 'quantity', 'surat_jalan_potong', 'temp_name']);

        $workers = Worker::potong()->get()->keyBy('id');

        return $rows
            ->groupBy('potong_id')
            ->map(function (Collection $group, $potongId) use ($workers) {
                $kitirCount = $group->count();
                $totalQty = (int) $group->sum('quantity');

                return (object) [
                    'worker_id' => (int) $potongId,
                    'worker_name' => $workers->get($potongId)?->name ?? 'Unknown',
                    'kitir_count' => $kitirCount,
                    'total_qty' => $totalQty,
                    'sjp_count' => $group->pluck('surat_jalan_potong')->filter()->unique()->count(),
                    'kode_count' => $group->pluck('temp_name')->filter()->unique()->count(),
                    'avg_qty' => $kitirCount > 0 ? round($totalQty / $kitirCount, 1) : 0.0,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();
    }

    /**
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    public function qcMonthlyTotals(int $year, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('qc_id')
                ->whereNotNull('qc_date')
                ->whereYear('qc_date', $year),
            $status
        )->get(['qc_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'qc_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, avg_qty: float, avg_potong_lag_days: ?float, avg_setor_lag_days: ?float}>
     */
    public function qcWorkerSummary(string $startDate, string $endDate, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('qc_id')
                ->whereDate('qc_date', '>=', $startDate)
                ->whereDate('qc_date', '<=', $endDate),
            $status
        )->get(['qc_id', 'quantity', 'qc_date', 'potong_date', 'setor_date']);

        $workers = Worker::qc()->get()->keyBy('id');

        return $rows
            ->groupBy('qc_id')
            ->map(function (Collection $group, $qcId) use ($workers) {
                $kitirCount = $group->count();
                $totalQty = (int) $group->sum('quantity');

                $potongLags = $group
                    ->filter(fn ($row) => $row->potong_date && $row->qc_date)
                    ->map(fn ($row) => Carbon::parse($row->potong_date)->diffInDays(Carbon::parse($row->qc_date)));

                $setorLags = $group
                    ->filter(fn ($row) => $row->setor_date && $row->qc_date)
                    ->map(fn ($row) => Carbon::parse($row->setor_date)->diffInDays(Carbon::parse($row->qc_date)));

                return (object) [
                    'worker_id' => (int) $qcId,
                    'worker_name' => $workers->get($qcId)?->name ?? 'Unknown',
                    'kitir_count' => $kitirCount,
                    'total_qty' => $totalQty,
                    'avg_qty' => $kitirCount > 0 ? round($totalQty / $kitirCount, 1) : 0.0,
                    'avg_potong_lag_days' => $potongLags->isNotEmpty() ? round($potongLags->avg(), 1) : null,
                    'avg_setor_lag_days' => $setorLags->isNotEmpty() ? round($setorLags->avg(), 1) : null,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();
    }

    /**
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    public function jahitMonthlyTotals(int $year, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('jahit_id')
                ->whereNotNull('jahit_date')
                ->whereYear('jahit_date', $year),
            $status
        )->get(['jahit_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'jahit_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, sjp_count: int, kode_count: int, avg_qty: float, avg_potong_lag_days: ?float}>
     */
    public function jahitWorkerSummary(string $startDate, string $endDate, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('jahit_id')
                ->whereDate('jahit_date', '>=', $startDate)
                ->whereDate('jahit_date', '<=', $endDate),
            $status
        )->get(['jahit_id', 'quantity', 'surat_jalan_potong', 'temp_name', 'jahit_date', 'potong_date']);

        $workers = Worker::jahit()->get()->keyBy('id');

        return $rows
            ->groupBy('jahit_id')
            ->map(function (Collection $group, $jahitId) use ($workers) {
                $kitirCount = $group->count();
                $totalQty = (int) $group->sum('quantity');

                $potongLags = $group
                    ->filter(fn ($row) => $row->potong_date && $row->jahit_date)
                    ->map(fn ($row) => Carbon::parse($row->potong_date)->diffInDays(Carbon::parse($row->jahit_date)));

                return (object) [
                    'worker_id' => (int) $jahitId,
                    'worker_name' => $workers->get($jahitId)?->name ?? 'Unknown',
                    'kitir_count' => $kitirCount,
                    'total_qty' => $totalQty,
                    'sjp_count' => $group->pluck('surat_jalan_potong')->filter()->unique()->count(),
                    'kode_count' => $group->pluck('temp_name')->filter()->unique()->count(),
                    'avg_qty' => $kitirCount > 0 ? round($totalQty / $kitirCount, 1) : 0.0,
                    'avg_potong_lag_days' => $potongLags->isNotEmpty() ? round($potongLags->avg(), 1) : null,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();
    }

    /**
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    public function pritilMonthlyTotals(int $year, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('pritil_id')
                ->whereNotNull('pritil_date')
                ->whereYear('pritil_date', $year),
            $status
        )->get(['pritil_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'pritil_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, avg_qty: float, avg_potong_lag_days: ?float, avg_setor_lag_days: ?float}>
     */
    public function pritilWorkerSummary(string $startDate, string $endDate, ?int $status = null): Collection
    {
        $rows = $this->applyStatusFilter(
            Produksi::query()
                ->whereNotNull('pritil_id')
                ->whereDate('pritil_date', '>=', $startDate)
                ->whereDate('pritil_date', '<=', $endDate),
            $status
        )->get(['pritil_id', 'quantity', 'pritil_date', 'potong_date', 'setor_date']);

        $workers = Worker::pritil()->get()->keyBy('id');

        return $rows
            ->groupBy('pritil_id')
            ->map(function (Collection $group, $pritilId) use ($workers) {
                $kitirCount = $group->count();
                $totalQty = (int) $group->sum('quantity');

                $potongLags = $group
                    ->filter(fn ($row) => $row->potong_date && $row->pritil_date)
                    ->map(fn ($row) => Carbon::parse($row->potong_date)->diffInDays(Carbon::parse($row->pritil_date)));

                $setorLags = $group
                    ->filter(fn ($row) => $row->setor_date && $row->pritil_date)
                    ->map(fn ($row) => Carbon::parse($row->setor_date)->diffInDays(Carbon::parse($row->pritil_date)));

                return (object) [
                    'worker_id' => (int) $pritilId,
                    'worker_name' => $workers->get($pritilId)?->name ?? 'Unknown',
                    'kitir_count' => $kitirCount,
                    'total_qty' => $totalQty,
                    'avg_qty' => $kitirCount > 0 ? round($totalQty / $kitirCount, 1) : 0.0,
                    'avg_potong_lag_days' => $potongLags->isNotEmpty() ? round($potongLags->avg(), 1) : null,
                    'avg_setor_lag_days' => $setorLags->isNotEmpty() ? round($setorLags->avg(), 1) : null,
                ];
            })
            ->sortByDesc('total_qty')
            ->values();
    }

    /**
     * @return object{kitir_count: int, total_qty: int, avg_qty: float, sjp_count: ?int, kode_count: ?int, avg_potong_lag_days: ?float, avg_setor_lag_days: ?float}
     */
    public function workerStats(Worker $worker, string $startDate, string $endDate): object
    {
        $fk = $worker->foreignKeyColumn();
        $dateCol = $worker->dateColumn();

        $rows = Produksi::query()
            ->where($fk, $worker->id)
            ->whereDate($dateCol, '>=', $startDate)
            ->whereDate($dateCol, '<=', $endDate)
            ->get(['quantity', 'surat_jalan_potong', 'temp_name', 'potong_date', 'qc_date', 'setor_date', 'pritil_date']);

        $kitirCount = $rows->count();
        $totalQty = (int) $rows->sum('quantity');

        $stats = (object) [
            'kitir_count' => $kitirCount,
            'total_qty' => $totalQty,
            'avg_qty' => $kitirCount > 0 ? round($totalQty / $kitirCount, 1) : 0.0,
            'sjp_count' => null,
            'kode_count' => null,
            'avg_potong_lag_days' => null,
            'avg_setor_lag_days' => null,
        ];

        if ($worker->type === Worker::TYPE_POTONG) {
            $stats->sjp_count = $rows->pluck('surat_jalan_potong')->filter()->unique()->count();
            $stats->kode_count = $rows->pluck('temp_name')->filter()->unique()->count();
        }

        if ($worker->type === Worker::TYPE_QC) {
            $potongLags = $rows
                ->filter(fn ($row) => $row->potong_date && $row->qc_date)
                ->map(fn ($row) => Carbon::parse($row->potong_date)->diffInDays(Carbon::parse($row->qc_date)));
            $setorLags = $rows
                ->filter(fn ($row) => $row->setor_date && $row->qc_date)
                ->map(fn ($row) => Carbon::parse($row->setor_date)->diffInDays(Carbon::parse($row->qc_date)));
            $stats->avg_potong_lag_days = $potongLags->isNotEmpty() ? round($potongLags->avg(), 1) : null;
            $stats->avg_setor_lag_days = $setorLags->isNotEmpty() ? round($setorLags->avg(), 1) : null;
        }

        return $stats;
    }

    public function workerHistory(Worker $worker, string $startDate, string $endDate, int $perPage = 20): LengthAwarePaginator
    {
        $fk = $worker->foreignKeyColumn();
        $dateCol = $worker->dateColumn();

        return Produksi::query()
            ->with(['potong', 'jahit', 'qc', 'pritil', 'size', 'item'])
            ->where($fk, $worker->id)
            ->whereDate($dateCol, '>=', $startDate)
            ->whereDate($dateCol, '<=', $endDate)
            ->latest($dateCol)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  Collection<int, Produksi>  $rows
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    protected function groupRowsByMonth(Collection $rows, string $dateColumn): Collection
    {
        $grouped = $rows
            ->groupBy(fn ($row) => (int) Carbon::parse($row->{$dateColumn})->month)
            ->map(fn (Collection $group, int $month) => (object) [
                'month' => $month,
                'kitir_count' => $group->count(),
                'total_qty' => (int) $group->sum('quantity'),
            ]);

        return collect(range(1, 12))->map(function (int $month) use ($grouped) {
            return $grouped->get($month) ?? (object) [
                'month' => $month,
                'kitir_count' => 0,
                'total_qty' => 0,
            ];
        });
    }

    protected function normalizeDateQuery(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function reportPeriodLabel(?int $month, int $year, ?string $from, ?string $to, ?int $status): string
    {
        $monthNames = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'];

        if ($from !== null || $to !== null) {
            $label = ($from ?? '…').' – '.($to ?? '…');
        } elseif ($month) {
            $label = ($monthNames[$month] ?? (string) $month).' '.$year;
        } else {
            $label = 'year '.$year;
        }

        if ($status !== null) {
            $label .= ' · '.Produksi::statusLabel($status);
        }

        return $label;
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Builder<\App\Models\Produksi>
     * @param  T  $query
     * @return T
     */
    protected function applyStatusFilter($query, ?int $status)
    {
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query;
    }
}
