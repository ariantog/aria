<?php

namespace App\Console\Commands;

use App\Models\JubelioProxyRequestLog;
use Illuminate\Console\Command;

class JubelioProxyLogsCommand extends Command
{
    protected $signature = 'jubelio-proxy:logs
                            {--client= : Filter by client name}
                            {--limit=20 : Number of rows}';

    protected $description = 'Inspect recent Jubelio proxy request audit logs';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));

        $query = JubelioProxyRequestLog::query()->orderByDesc('id');

        $clientName = $this->option('client');
        if (is_string($clientName) && $clientName !== '') {
            $query->where('client_name', $clientName);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->line('No proxy logs found.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'When (WIB)', 'Client', 'API', 'Jubelio', 'ms', 'Error'],
            $rows->map(fn (JubelioProxyRequestLog $log) => [
                $log->id,
                $log->requested_at?->timezone('Asia/Jakarta')->toDateTimeString(),
                $log->client_name,
                $log->api_name,
                $log->jubelio_status ?? '—',
                $log->duration_ms,
                $log->error ?? '',
            ])->all(),
        );

        return self::SUCCESS;
    }
}