<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\FixedAssetService;
use App\Services\MonthlyDepreciationRunner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class RunMonthlyDepreciation extends Command
{
    protected $signature = 'app:run-monthly-depreciation
                            {--month= : Month to post (Y-m). Defaults to the previous month}
                            {--expense= : Depreciation expense account id}
                            {--contra= : Accumulated depreciation account id}';

    protected $description = 'Post monthly asset-tetap depreciation transactions (type 18)';

    public function handle(MonthlyDepreciationRunner $runner): int
    {
        $monthOption = $this->option('month');
        $month = $monthOption
            ? Carbon::createFromFormat('Y-m', (string) $monthOption)->startOfMonth()
            : Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $expenseId = (int) ($this->option('expense') ?: Setting::getValue(FixedAssetService::SETTING_EXPENSE_ACCOUNT, 0));
        $contraId = (int) ($this->option('contra') ?: Setting::getValue(FixedAssetService::SETTING_CONTRA_ACCOUNT, 0));

        if ($expenseId <= 0 || $contraId <= 0) {
            $this->error('Set akun beban and akumulasi penyusutan in System Settings, or pass --expense and --contra.');

            return self::FAILURE;
        }

        try {
            $result = $runner->run($month, $expenseId, $contraId);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        if ($result['transaction'] === null) {
            $this->info('No asset tetap lines to depreciate for '.$month->format('Y-m').'.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Posted depreciation %s for %s (%d assets, total %s).',
            $result['transaction']->invoice,
            $month->format('Y-m'),
            $result['posted'],
            number_format((float) $result['transaction']->total, 2, '.', '')
        ));

        return self::SUCCESS;
    }
}
