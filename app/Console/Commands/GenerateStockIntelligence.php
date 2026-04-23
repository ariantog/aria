<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Setting;
use App\Models\StockData;
use App\Models\StokReport;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateStockIntelligence extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-stock-intelligence {--type=cron} {--user_id=} {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate stock intelligence report and store it in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $userId = $this->option('user_id');
        $dateOption = $this->option('date');

        $referenceDate = $dateOption ? \Illuminate\Support\Carbon::parse($dateOption) : now();
        $referenceDateSql = $referenceDate->toDateString();

        // Only check allowed days if it's a standard cron run without a specific date override
        if ($type === 'cron' && ! $dateOption) {
            $allowedEnglishDays = Setting::getValue('si_generate_days', []);
            $currentEnglishDay = $referenceDate->format('l'); // e.g. "Monday"

            if (! empty($allowedEnglishDays)) {
                if (! in_array($currentEnglishDay, $allowedEnglishDays)) {
                    $this->info("Today is {$currentEnglishDay}, which is not in the allowed generation days (stored as English in DB).");

                    return Command::SUCCESS;
                }
            }
        }

        // Security check: Skip if report for this date already exists
        $exists = StokReport::whereDate('generet_at', $referenceDateSql)->exists();
        if ($exists) {
            $this->warn("Generation skipped: A stock report for date {$referenceDateSql} already exists.");

            return Command::SUCCESS;
        }

        $this->info("Starting stock intelligence generation for reference date: {$referenceDateSql}...");

        $sellType = Transaction::TYPE_SELL;

        $gw = (float) Setting::getValue('si_gap_weight', 0.2);
        $sw = (float) Setting::getValue('si_sale_weight', 0.8);
        $mg = (float) Setting::getValue('si_max_gap', 90);
        $md = (float) Setting::getValue('si_max_days', 90);

        $baseQuery = DB::table('warehouse_items as wi')
            ->join('items as i', 'wi.item_id', '=', 'i.id')
            ->join('addrbooks as a', 'wi.warehouse_id', '=', 'a.id')
            ->leftJoin(DB::raw("(
                SELECT item_id, sender_id, MAX(date) AS last_sale_date
                FROM   transaction_details
                WHERE  transaction_type = ? AND date <= '{$referenceDateSql}'
                GROUP  BY item_id, sender_id
            ) ls"), function ($j) {
                $j->on('ls.item_id', '=', 'wi.item_id')
                    ->on('ls.sender_id', '=', 'wi.warehouse_id');
            })
            ->leftJoin(DB::raw("(
                SELECT
                    td.item_id,
                    td.sender_id                       AS best_warehouse_id,
                    td.date                            AS best_sale_date,
                    a2.name                            AS best_warehouse_name,
                    DATEDIFF('{$referenceDateSql}', td.date)       AS best_days_ago
                FROM transaction_details td
                JOIN addrbooks a2 ON a2.id = td.sender_id
                WHERE td.transaction_type = ? AND td.date <= '{$referenceDateSql}'
                  AND (td.item_id, td.date, td.id) IN (
                        SELECT item_id, MAX(date), MAX(id)
                        FROM   transaction_details
                        WHERE  transaction_type = ? AND date <= '{$referenceDateSql}'
                        GROUP  BY item_id
                      )
            ) gb"), 'gb.item_id', '=', 'wi.item_id')
            ->leftJoin('warehouse_items as wibest', function ($j) {
                $j->on('wibest.item_id', '=', 'wi.item_id')
                    ->on('wibest.warehouse_id', '=', 'gb.best_warehouse_id');
            })
            ->where('wi.quantity', '>', 0)
            ->where('a.type', Addrbook::TYPE_WAREHOUSE)
            ->select(
                'wi.item_id',
                'i.name                          as item_name',
                'wi.warehouse_id',
                'a.name                          as warehouse_name',
                'wi.quantity                     as current_qty',
                'ls.last_sale_date',
                DB::raw("CASE WHEN ls.last_sale_date IS NOT NULL
                         THEN DATEDIFF('{$referenceDateSql}', ls.last_sale_date)
                         ELSE NULL END                               AS days_ago"),
                'gb.best_warehouse_id',
                'gb.best_warehouse_name',
                'gb.best_sale_date',
                'gb.best_days_ago',
                DB::raw('COALESCE(wibest.quantity, 0)                   AS best_warehouse_qty')
            )
            ->addBinding([$sellType, $sellType, $sellType], 'join');

        $baseSql = $baseQuery->toSql();
        $baseBindings = $baseQuery->getBindings();

        $scoredSql = "
        SELECT
            base.*,
            CASE
                WHEN base.days_ago IS NULL      THEN 0
                WHEN base.days_ago > {$md}      THEN 0
                WHEN base.best_days_ago IS NOT NULL THEN
                    ROUND(
                          GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / {$mg})) * {$gw}
                        + GREATEST(0, LEAST(1, 1 - base.days_ago / {$md})) * {$sw}
                    , 4)
                ELSE 0
            END AS score,
            CASE
                WHEN base.days_ago IS NULL THEN 'critical'
                WHEN base.days_ago > {$md} THEN 'deadstock'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / {$mg})) * {$gw}
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / {$md})) * {$sw}) >= 0.90 THEN 'elite'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / {$mg})) * {$gw}
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / {$md})) * {$sw}) >= 0.70 THEN 'good'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / {$mg})) * {$gw}
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / {$md})) * {$sw}) >= 0.50 THEN 'active'
                WHEN base.best_days_ago IS NOT NULL AND
                    (GREATEST(0, LEAST(1, 1 - (base.days_ago - base.best_days_ago) / {$mg})) * {$gw}
                   + GREATEST(0, LEAST(1, 1 - base.days_ago / {$md})) * {$sw}) >= 0.30 THEN 'lagging'
                WHEN base.best_days_ago IS NOT NULL THEN 'stagnant'
                ELSE 'critical'
            END AS perf_key
        FROM ({$baseSql}) AS base
        ";

        $rows = DB::table(DB::raw("({$scoredSql}) as scored"))
            ->addBinding($baseBindings, 'join')
            ->orderByDesc('score')
            ->limit(Setting::getValue('si_total_rows', 1000))
            ->get();

        $perfLabels = [
            'elite' => '1. Elite (Terbaik)',
            'good' => '2. Good (Aktif)',
            'active' => '3. Active (Normal)',
            'lagging' => '4. Lagging (Lambat)',
            'stagnant' => '5. Stagnant (Sangat Lambat)',
            'deadstock' => '6. Deadstock (Mati)',
            'critical' => '7. Critical (Belum Terjual)',
        ];

        if ($rows->isEmpty()) {
            $this->warn('No items found for stock intelligence generation.');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($rows, $perfLabels, $type, $userId, $referenceDate, $referenceDateSql) {
            $report = StokReport::create([
                'generet_at' => $referenceDate,
                'type' => $type,
                'generet_by' => $userId,
            ]);

            foreach ($rows as $r) {
                $gapDays = match (true) {
                    $r->last_sale_date === null => null,
                    $r->best_days_ago !== null => (int) $r->days_ago - (int) $r->best_days_ago,
                    default => 9999,
                };

                StockData::create([
                    'id_stock_report' => $report->id,
                    'item_id' => $r->item_id,
                    'item_name' => $r->item_name,
                    'score' => (float) $r->score,
                    'performance_key' => $r->perf_key,
                    'performance_level' => $perfLabels[$r->perf_key] ?? $r->perf_key,
                    'gap_days' => $gapDays,
                    'current_warehouse_id' => $r->warehouse_id,
                    'current_warehouse_name' => $r->warehouse_name,
                    'current_warehouse_qty' => (int) $r->current_qty,
                    'current_warehouse_last_sale' => $r->last_sale_date,
                    'current_warehouse_days_ago' => $r->days_ago,
                    'best_performing_warehouse_id' => $r->best_warehouse_id,
                    'best_performing_warehouse_name' => $r->best_warehouse_name,
                    'best_performing_warehouse_last_sale' => $r->best_sale_date,
                    'best_performing_warehouse_days_ago' => $r->best_days_ago,
                    'best_performing_warehouse_qty' => (int) $r->best_warehouse_qty,
                    'audit_reference_date' => $referenceDateSql,
                ]);
            }
        });

        $this->info('Stock intelligence generation completed successfully.');

        return Command::SUCCESS;
    }
}
