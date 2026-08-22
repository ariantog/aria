<?php

namespace App\Console\Commands;

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderJubelioToAria extends Command
{
    protected $signature = 'jubelio:order-jubelio-to-aria {--all : Process all pending orders} {--truncate : Delete existing transactions and reset all orders to pending}';

    protected $description = 'Proses order jubelio ke aria transaction';

    public function handle(ProcessJubelioOrder $processor): int
    {
        Log::info('V2 - Proses order Jubelio ke Aria Transaction dijalankan pada: '.now());

        if ($this->option('truncate')) {
            $this->warn('Hapus data transaksi lama dan reset status order...');

            DB::transaction(function () {
                DB::table('transaction_details')
                    ->whereIn('transaction_id', function ($query) {
                        $query->select('id')->from('transactions')->where('submit_type', 2);
                    })->delete();

                DB::table('transactions')->where('submit_type', 2)->delete();

                DB::table('jubelioorders')->update([
                    'status' => 0,
                    'run_count' => 0,
                    'error' => null,
                    'error_type' => null,
                    'execute_by' => null,
                ]);
            });

            $this->info('Reset data selesai.');
        }

        $query = Jubelioorder::whereIn('type', ['SELL', 'RETURN'])
            ->where('status', 0)
            ->where('run_count', 0)
            ->whereNull('execute_by');

        if ($this->option('all')) {
            $this->info('Processing ALL pending orders using chunks...');
            $query->orderBy('id', 'asc')->chunkById(500, function ($orders) use ($processor) {
                foreach ($orders as $order) {
                    $processor->execute($order);
                }
            });
        } else {
            $order = $query->orderBy('created_at', 'asc')->first();

            if (! $order) {
                Log::info('Tidak ada antrian order Jubelio untuk diproses.');
                $this->info('No pending orders.');

                return self::SUCCESS;
            }

            $result = $processor->execute($order);
            $this->info($result['message']);
        }

        $this->info('Process completed.');

        return self::SUCCESS;
    }
}
