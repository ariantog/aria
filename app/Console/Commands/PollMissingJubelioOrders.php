<?php

namespace App\Console\Commands;

use App\Services\JubelioGetOrdersService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollMissingJubelioOrders extends Command
{
    protected $signature = 'jubelio:poll-missing-orders {--days= : Override poll window in days}';

    protected $description = 'Poll Jubelio for recent orders missing from Aria (catches failed webhooks)';

    public function handle(JubelioGetOrdersService $service): int
    {
        if (! config('services.jubelio.active')) {
            $this->comment('Jubelio integration is inactive.');

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : null;

        Log::info('jubelio:poll-missing-orders run at: '.now());

        try {
            $queued = $service->pollRecentDays($days);
            $this->info("Queued {$queued} missing order(s).");

            return self::SUCCESS;
        } catch (\Exception $e) {
            Log::error('jubelio:poll-missing-orders failed', [
                'message' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
