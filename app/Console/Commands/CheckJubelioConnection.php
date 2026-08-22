<?php

namespace App\Console\Commands;

use App\Services\JubelioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckJubelioConnection extends Command
{
    protected $signature = 'jubelio:check-connection';

    protected $description = 'Refresh Jubelio token and verify API connectivity';

    public function handle(JubelioService $jubelioService): int
    {
        if (! config('services.jubelio.active')) {
            $this->comment('Jubelio integration is inactive (JUBELIO_ACTIVE=false).');

            return self::SUCCESS;
        }

        Log::info('jubelio:check-connection run at: '.now());

        $result = $jubelioService->checkConnection();

        if ($result['ok']) {
            $this->info($result['message']);

            return self::SUCCESS;
        }

        $this->error($result['message']);

        return self::FAILURE;
    }
}
