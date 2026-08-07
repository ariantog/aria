<?php

namespace App\Actions\Jubelio;

use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Services\JubelioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            $transaction->increment('submit_a_count');
        } else {
            if ($transaction->b_submit_by) {
                throw new \RuntimeException('Side B already synced.');
            }
            if ($transaction->hasSyncWarningB()) {
                throw new \RuntimeException('Side B has a pending sync warning. Confirm or clear it first.');
            }
            $transaction->increment('submit_b_count');
        }

        try {
            DB::beginTransaction();
            $warehouseId = $whType === 1 ? $transaction->receiver_id : $transaction->sender_id;
            $jubSync = Jubeliosync::where('warehouse_id', $warehouseId)->first();
            if (! $jubSync) {
                throw new \RuntimeException('Jubelio mapping not found.');
            }

            config(['services.jubelio.active' => true, 'services.jubelio.verify_ssl' => false]);
            $http = app(JubelioService::class)->authenticatedRequest();
            if (! $http) {
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
                    'bin_id' => $jubSync->bin_id,
                    'cost' => 0,
                ];
            }

            if ($items === []) {
                throw new \RuntimeException('No linked Jubelio items to sync.');
            }

            $response = $http->post('https://api2.jubelio.com/inventory/adjustments/warehouse', [
                'item_adj_id' => 0,
                'item_adj_no' => '[auto]',
                'transaction_date' => now()->toIso8601ZuluString(),
                'note' => 'Adjust from Aria #'.$transaction->invoice_number,
                'location_id' => $jubSync->jubelio_location_id,
                'is_opening_balance' => false,
                'items' => $items,
            ]);

            if ($response->successful()) {
                $result = $response->json();
                $referenceId = $result['id'] ?? null;
                if (! $referenceId) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => 'Respons API Jubelio tidak jelas (tidak ada reference ID). Konfirmasi atau hapus peringatan sinkronisasi.',
                    ];
                }

                if ($side === 1) {
                    $transaction->update(['a_submit_by' => Auth::id(), 'a_reference_id' => $referenceId]);
                } else {
                    $transaction->update(['b_submit_by' => Auth::id(), 'b_reference_id' => $referenceId]);
                }
                DB::commit();

                return ['success' => true, 'message' => 'Jubelio adjustment successful.'];
            }

            DB::rollBack();
            $err = $response->json();

            return ['success' => false, 'message' => $err['message'] ?? 'API Error: '.$response->status()];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
