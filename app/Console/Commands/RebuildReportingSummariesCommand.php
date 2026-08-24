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
                            {--year= : Optional year filter (overrides --from when set)}';

    protected $description = 'Rebuild reporting entity, operation, and tax summaries from completed transactions';

    public function handle(ReportingSummaryRecorder $recorder): int
    {
        $from = $this->resolveFromDate();
        $to = $this->option('to') ? Carbon::parse($this->option('to'))->endOfDay() : null;

        $this->info(sprintf(
            'Rebuilding reporting summaries from %s%s…',
            $from->toDateString(),
            $to ? ' through '.$to->toDateString() : '',
        ));

        ReportingEntityMonthlySummary::query()->delete();
        ReportingOperationMonthlySummary::query()->delete();
        ReportingMonthlyTaxSummary::query()->delete();

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

    private function resolveFromDate(): Carbon
    {
        if ($year = $this->option('year')) {
            $cutover = Carbon::parse(config('reporting.cutover_date'))->startOfDay();
            $yearStart = Carbon::create((int) $year)->startOfYear();

            return $yearStart->greaterThan($cutover) ? $yearStart : $cutover;
        }

        $from = $this->option('from') ?: config('reporting.cutover_date');

        return Carbon::parse($from)->startOfDay();
    }
}
