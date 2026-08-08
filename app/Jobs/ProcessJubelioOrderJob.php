<?php

namespace App\Jobs;

use App\Actions\Jubelio\ProcessJubelioOrder;
use App\Models\Jubelioorder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessJubelioOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct(public int $jubelioOrderId) {}

    public function uniqueId(): string
    {
        return 'jubelio-order-'.$this->jubelioOrderId;
    }

    public function handle(ProcessJubelioOrder $processor): void
    {
        $order = Jubelioorder::find($this->jubelioOrderId);

        if (! $order || $order->status !== 0) {
            return;
        }

        $result = $processor->execute($order);

        Log::info('ProcessJubelioOrderJob finished', [
            'jubelio_order_id' => $this->jubelioOrderId,
            'invoice' => $order->invoice,
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }
}
