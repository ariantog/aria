<?php

namespace App\Services\Produksi;

use App\Models\Produksi;
use App\Models\Worker;
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
     * @return Collection<int, object{month: int, kitir_count: int, total_qty: int}>
     */
    public function potongMonthlyTotals(int $year): Collection
    {
        $rows = Produksi::query()
            ->whereNotNull('potong_id')
            ->whereNotNull('potong_date')
            ->whereYear('potong_date', $year)
            ->get(['potong_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'potong_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, sjp_count: int, kode_count: int, avg_qty: float}>
     */
    public function potongWorkerSummary(string $startDate, string $endDate): Collection
    {
        $rows = Produksi::query()
            ->whereNotNull('potong_id')
            ->whereDate('potong_date', '>=', $startDate)
            ->whereDate('potong_date', '<=', $endDate)
            ->get(['potong_id', 'quantity', 'surat_jalan_potong', 'temp_name']);

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
    public function qcMonthlyTotals(int $year): Collection
    {
        $rows = Produksi::query()
            ->whereNotNull('qc_id')
            ->whereNotNull('qc_date')
            ->whereYear('qc_date', $year)
            ->get(['qc_date', 'quantity']);

        return $this->groupRowsByMonth($rows, 'qc_date');
    }

    /**
     * @return Collection<int, object{worker_id: int, worker_name: string, kitir_count: int, total_qty: int, avg_qty: float, avg_potong_lag_days: ?float, avg_setor_lag_days: ?float}>
     */
    public function qcWorkerSummary(string $startDate, string $endDate): Collection
    {
        $rows = Produksi::query()
            ->whereNotNull('qc_id')
            ->whereDate('qc_date', '>=', $startDate)
            ->whereDate('qc_date', '<=', $endDate)
            ->get(['qc_id', 'quantity', 'qc_date', 'potong_date', 'setor_date']);

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
}
