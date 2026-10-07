<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearJubelioOrderPayloads extends Command
{
    protected $signature = 'app:clear-jubelio-order-payloads
                            {--type=all : RETURN, SELL, or all}
                            {--chunk=500 : Rows per UPDATE batch}
                            {--dry-run : Count rows only, do not update}';

    protected $description = 'Set jubelioorders.payload to NULL (legacy JSON bloat). New rows already omit payload; Aria loads from Jubelio API.';

    public function handle(): int
    {
        $type = strtoupper((string) $this->option('type'));
        if (! in_array($type, ['RETURN', 'SELL', 'ALL'], true)) {
            $this->error('Invalid --type; use RETURN, SELL, or all.');

            return self::FAILURE;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::table('jubelioorders')
            ->whereNotNull('payload')
            ->where('payload', '!=', '');

        if ($type !== 'ALL') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No jubelioorders rows with stored payload.');

            return self::SUCCESS;
        }

        $label = $type === 'ALL' ? 'all types' : $type;
        if ($dryRun) {
            $this->info("Would clear payload on {$total} {$label} row(s).");

            return self::SUCCESS;
        }

        $cleared = 0;
        $query->orderBy('id')->select('id')->chunkById($chunk, function ($rows) use (&$cleared) {
            $ids = $rows->pluck('id')->all();
            $cleared += DB::table('jubelioorders')
                ->whereIn('id', $ids)
                ->update(['payload' => null]);
        });

        $this->info("Cleared payload on {$cleared} {$label} row(s).");

        return self::SUCCESS;
    }
}
