<?php

namespace App\Actions\Jubelio;

use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Services\Jubelio\JubelioAdjustmentResponse;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdjustStock
{
    public function execute(Transaction $transaction, int $side, int $adjustType, int $whType): array
    {
        if ($side === 1) {
            if ($transaction->a_submit_by) {
                throw new \RuntimeException('Side A already synced.');
            }
            if ($transaction->hasSyncWarningA()) {
                throw new \RuntimeException('Side A has a pending sync warning. Confirm or clear it first.');
            }
        } else {
            if ($transaction->b_submit_by) {
                throw new \RuntimeException('Side B already synced.');
            }
            if ($transaction->hasSyncWarningB()) {
                throw new \RuntimeException('Side B has a pending sync warning. Confirm or clear it first.');
            }
        }

        $posted = false;

        try {
            $warehouseId = $whType === 1 ? $transaction->receiver_id : $transaction->sender_id;
            $jubSync = Jubeliosync::where('warehouse_id', $warehouseId)->first();
            if (! $jubSync) {
                throw new \RuntimeException('Jubelio mapping not found.');
            }

            config(['services.jubelio.active' => true, 'services.jubelio.verify_ssl' => false]);
            $jubelioService = app(JubelioService::class);
            if (! $jubelioService->authenticatedRequest()) {
                throw new \RuntimeException('Jubelio auth failed.');
            }

            $transaction->loadMissing('details.item');
            $items = [];
            foreach ($transaction->details as $row) {
                if (! $row->item?->jubelio_item_id) {
                    continue;
                }
                $qty = $adjustType === 1 ? (float) $row->quantity : -(float) $row->quantity;
                $items[] = [
                    'item_adj_detail_id' => 0,
                    'item_id' => $row->item->jubelio_item_id,
                    'serial_no' => null,
                    'qty_in_base' => $qty,
                    'original_item_adj_detail_id' => 0,
                    'unit' => 'Buah',
                    'amount' => (float) $row->total,
                    'location_id' => $jubSync->jubelio_location_id,
                    'account_id' => 75,
                    'description' => 'Item '.$row->item->code,
                    'bin_id' => ((int) $jubSync->bin_id) > 0 ? (int) $jubSync->bin_id : null,
                    'cost' => 0,
                ];
            }

            if ($items === []) {
                throw new \RuntimeException('No linked Jubelio items to sync.');
            }

            $this->markAttempt($transaction, $side);
            $posted = true;

            $response = $jubelioService->post('https://api2.jubelio.com/inventory/adjustments/warehouse', [
                'item_adj_id' => 0,
                'item_adj_no' => '[auto]',
                'transaction_date' => now()->toIso8601ZuluString(),
                'note' => 'Adjust from Aria #'.$transaction->invoice,
                'location_id' => $jubSync->jubelio_location_id,
                'is_opening_balance' => false,
                'items' => $items,
            ]);

            if (! $response) {
                $this->logOutcome($transaction, $side, null, null, 'no-response');

                return [
                    'success' => false,
                    'message' => 'Tidak ada respons dari Jubelio setelah push. Jangan tandai berhasil — konfirmasi di Jubelio atau hapus peringatan untuk coba lagi.',
                ];
            }

            $parsed = JubelioAdjustmentResponse::fromHttp(
                $response->status(),
                $response->json(),
                $response->body(),
            );
            $this->logOutcome($transaction, $side, $response->status(), $response->body(), $parsed->outcome);

            if ($parsed->created()) {
                DB::transaction(function () use ($transaction, $side, $parsed) {
                    if ($side === 1) {
                        $transaction->update([
                            'a_submit_by' => Auth::id(),
                            'a_reference_id' => $parsed->referenceId,
                        ]);
                    } else {
                        $transaction->update([
                            'b_submit_by' => Auth::id(),
                            'b_reference_id' => $parsed->referenceId,
                        ]);
                    }
                });

                return ['success' => true, 'message' => 'Jubelio adjustment successful.'];
            }

            if ($parsed->failed()) {
                $this->clearAttempt($transaction, $side);

                return [
                    'success' => false,
                    'message' => $parsed->message ?? 'API Error: '.$response->status(),
                ];
            }

            return [
                'success' => false,
                'message' => $parsed->message ?? 'Respons API Jubelio tidak jelas (tidak ada reference ID). Jangan tandai berhasil sebelum ada nomor penyesuaian di Jubelio.',
            ];
        } catch (\Exception $e) {
            if (! $posted) {
                $this->clearAttempt($transaction, $side);
            }

            if ($posted) {
                $this->logOutcome($transaction, $side, null, $e->getMessage(), 'exception-after-post');
            }

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function markAttempt(Transaction $transaction, int $side): void
    {
        if ($side === 1) {
            $transaction->increment('submit_a_count');
        } else {
            $transaction->increment('submit_b_count');
        }
    }

    private function clearAttempt(Transaction $transaction, int $side): void
    {
        if ($side === 1) {
            if ($transaction->a_submit_by) {
                return;
            }
            $transaction->update(['submit_a_count' => 0]);
        } else {
            if ($transaction->b_submit_by) {
                return;
            }
            $transaction->update(['submit_b_count' => 0]);
        }
    }

    private function logOutcome(Transaction $transaction, int $side, ?int $status, ?string $body, string $outcome): void
    {
        $context = [
            'transaction_id' => $transaction->id,
            'invoice' => $transaction->invoice,
            'side' => $side,
            'http_status' => $status,
            'outcome' => $outcome,
            'body' => $body !== null ? Str::limit($body, 500) : null,
        ];

        if ($outcome === JubelioAdjustmentResponse::OUTCOME_CREATED) {
            Log::info('Jubelio stock adjustment created.', $context);
        } elseif ($outcome === JubelioAdjustmentResponse::OUTCOME_FAILED) {
            Log::error('Jubelio stock adjustment failed.', $context);
        } else {
            Log::warning('Jubelio stock adjustment status unclear.', $context);
        }
    }
}
