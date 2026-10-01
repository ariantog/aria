<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\UserActivityAuditService;
use Illuminate\Console\Command;

class UserActivityAudit extends Command
{
    protected $signature = 'app:user-activity-audit
                            {--month= : Calendar month to audit (Y-m), default current month}
                            {--user= : Filter by user id}
                            {--late-days= : Days after txn date to flag late entry}
                            {--min-count= : Minimum count for frequent-user lists}
                            {--include-jubelio : Include Jubelio cron rows}';

    protected $description = 'Summarize suspicious user activity (late/backdated txns, virtual moves, discounted sells)';

    public function handle(UserActivityAuditService $audit): int
    {
        $filters = $audit->resolveFilters([
            'month' => $this->option('month'),
            'user_id' => $this->option('user'),
            'late_entry_days' => $this->option('late-days'),
            'frequent_min_count' => $this->option('min-count'),
            'exclude_jubelio' => ! $this->option('include-jubelio'),
        ]);

        $this->info(sprintf(
            'Bulan %s (%s → %s) | tutup buku mulai %s | late ≥ %d hari | frequent ≥ %d',
            $filters['month'],
            $filters['from'],
            $filters['to'],
            $filters['min_allowed_date'],
            $filters['late_entry_days'],
            $filters['frequent_min_count'],
        ));

        $this->newLine();
        $this->comment('Suspicious timing (sample up to 20):');
        $sample = $audit->suspiciousTimingQuery($filters)->with('user')->orderByDesc('id')->limit(20)->get();
        if ($sample->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($sample as $txn) {
                $flags = implode('; ', $audit->timingFlags($txn, $filters));
                $this->line(sprintf(
                    '  #%d %s user=%s date=%s created=%s — %s',
                    $txn->id,
                    Transaction::typeLabel((int) $txn->type),
                    $txn->user?->username ?? $txn->user_id,
                    $txn->date?->toDateString() ?? $txn->date,
                    $txn->created_at?->toDateTimeString(),
                    $flags,
                ));
            }
        }

        $this->newLine();
        $this->comment('Frequent moves → virtual warehouse:');
        $this->table(
            ['user_id', 'username', 'name', 'move_count'],
            $audit->frequentVirtualWarehouseMoves($filters),
        );

        $this->newLine();
        $this->comment('Frequent sells with discount or Rp 0 total:');
        $this->table(
            ['user_id', 'username', 'name', 'sell_count', 'zero_total_count', 'discounted_count'],
            $audit->frequentDiscountedOrZeroSells($filters),
        );

        return self::SUCCESS;
    }
}
