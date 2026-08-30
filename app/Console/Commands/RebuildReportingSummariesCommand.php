<?php

namespace App\Console\Commands;

use App\Models\ReportingEntityMonthlySummary;
use App\Models\ReportingMonthlyTaxSummary;
use App\Models\ReportingOperationMonthlySummary;
use App\Models\Transaction;
use App\Services\Reporting\ReportingSummaryRecorder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RebuildReportingSummariesCommand extends Command
{
    protected $signature = 'reporting:rebuild-summaries
                            {--from= : Start date (default: reporting cutover)}
                            {--to= : Optional end date}
                            {--year= : Optional year filter (overrides --from when set)}
                            {--months= : Rebuild only the last N months without truncating older summaries}';

    protected $description = 'Rebuild reporting entity, operation, and tax summaries from completed transactions';

    public function handle(ReportingSummaryRecorder $recorder): int
    {
        [$from, $to, $scoped] = $this->resolveRange();

        $this->info(sprintf(
            '%s reporting summaries from %s%s…',
            $scoped ? 'Rebuilding recent' : 'Rebuilding',
            $from->toDateString(),
            $to ? ' through '.$to->toDateString() : '',
        ));

        if ($scoped && $to) {
            $this->deleteSummariesInRange($from, $to);
        } else {
            ReportingEntityMonthlySummary::query()->delete();
            ReportingOperationMonthlySummary::query()->delete();
            ReportingMonthlyTaxSummary::query()->delete();
        }

        $query = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('date', '>=', $from->toDateString())
            ->when($to, fn ($builder) => $builder->whereDate('date', '<=', $to->toDateString()))
            ->orderBy('date')
            ->orderBy('id');

        $total = (clone $query)->count();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $query->chunkById(500, function ($transactions) use ($recorder, $bar, &$processed) {
            foreach ($transactions as $transaction) {
                $recorder->record($transaction);
                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Replayed {$processed} completed transactions.");

        return Command::SUCCESS;
    }

    /**
     * @return array{0: Carbon, 1: ?Carbon, 2: bool}
     */
    private function resolveRange(): array
    {
        $cutover = Carbon::parse(config('reporting.cutover_date'))->startOfDay();

        if ($months = $this->option('months')) {
            $count = max(1, (int) $months);
            $from = now()->startOfMonth()->subMonths($count - 1);
            $to = now()->endOfMonth();
            if ($from->lessThan($cutover)) {
                $from = $cutover->copy();
            }

            return [$from, $to, true];
        }

        if ($year = $this->option('year')) {
            $yearStart = Carbon::create((int) $year)->startOfYear();

            return [$yearStart->greaterThan($cutover) ? $yearStart : $cutover, null, false];
        }

        $from = $this->option('from') ?: config('reporting.cutover_date');

        return [
            Carbon::parse($from)->startOfDay(),
            $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null,
            false,
        ];
    }

    private function deleteSummariesInRange(Carbon $from, Carbon $to): void
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $months[] = [(int) $cursor->year, (int) $cursor->month];
            $cursor->addMonth();
        }

        foreach ([
            ReportingEntityMonthlySummary::class,
            ReportingOperationMonthlySummary::class,
            ReportingMonthlyTaxSummary::class,
        ] as $model) {
            $model::query()
                ->where(function ($query) use ($months) {
                    foreach ($months as [$year, $month]) {
                        $query->orWhere(function ($inner) use ($year, $month) {
                            $inner->where('year', $year)->where('month', $month);
                        });
                    }
                })
                ->delete();
        }
    }
}
