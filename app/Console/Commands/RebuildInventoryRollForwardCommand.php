<?php

namespace App\Console\Commands;

use App\Services\Reporting\InventoryRollForwardService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RebuildInventoryRollForwardCommand extends Command
{
    protected $signature = 'reporting:rebuild-inventory
                            {--from= : Start month Y-m (default: persediaan start)}
                            {--to= : End month Y-m (default: current month)}';

    protected $description = 'Rebuild persisted persediaan monthly roll-forward from January 2026';

    public function handle(InventoryRollForwardService $inventory): int
    {
        $start = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfMonth()
            : $inventory->startDate();
        $end = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfMonth()
            : now()->startOfMonth();

        if ($start->lt($inventory->startDate())) {
            $start = $inventory->startDate();
        }

        if ($end->lt($start)) {
            $this->error('End month is before start month.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Rebuilding persediaan roll-forward from %s through %s (opening seed %s)…',
            $start->format('Y-m'),
            $end->format('Y-m'),
            number_format($inventory->openingSeed(), 2, '.', ''),
        ));

        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $row = $inventory->forMonth($cursor->year, $cursor->month);
            $this->line(sprintf(
                '  %04d-%02d  open %s  buy %s  cogs %s  gaji %s  material-out %s  close %s',
                $row['year'],
                $row['month'],
                $this->money($row['opening']),
                $this->money($row['material_purchases']),
                $this->money($row['cogs']),
                $this->money($row['production_cost']),
                $this->money($row['material_cash_out']),
                $this->money($row['closing']),
            ));
            $cursor->addMonth();
        }

        return self::SUCCESS;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
