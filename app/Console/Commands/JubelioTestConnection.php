<?php

namespace App\Console\Commands;

use App\Services\JubelioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class JubelioTestConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:jubelio-test-connection {--verify-ssl : Enable SSL verification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Jubelio API connection and fetch inventory data as per plan/jubelio_getstock.txt';

    /**
     * Execute the console command.
     */
    public function handle(JubelioService $jubelioService): int
    {
        // Ensure service is active for this test command
        config(['services.jubelio.active' => true]);
        
        if (!$this->option('verify-ssl')) {
            config(['services.jubelio.verify_ssl' => false]);
        }

        $token = $jubelioService->authenticate()['token'] ?? null;

        if (!$token) {
            $this->line(json_encode(['error' => 'Authentication failed']));
            return 1;
        }

        $request = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json']);

        if (!$this->option('verify-ssl')) {
            $request->withoutVerifying();
        }

        $response = $request->get('https://api2.jubelio.com/inventory/', [
            'page' => 1,
            'pageSize' => 200,
            'sortDirection' => 'ASC',
            'sortBy' => 'name',
            'csv' => 'true',
            'q' => 'string'
        ]);

        if ($response->successful()) {
            $this->line(json_encode($response->json()));
            return 0;
        }

        $this->line(json_encode([
            'error' => 'API request failed',
            'status' => $response->status(),
            'body' => $response->json()
        ]));

        return 1;
    }
}
