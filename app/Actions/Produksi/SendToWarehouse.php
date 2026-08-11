<?php

namespace App\Actions\Produksi;

use App\Enums\AddrbookType;
use App\Enums\TransactionType;
use App\Models\Addrbook;
use App\Models\Produksi;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\InventoryService;
use App\Services\Produksi\ProduksiSettingsService;
use Illuminate\Support\Facades\DB;

class SendToWarehouse
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ProduksiSettingsService $produksiSettings,
    ) {}

    public function execute(Produksi $produksi, string $invoice, int $userId): void
    {
        DB::transaction(function () use ($produksi, $invoice, $userId) {
            if (empty($produksi->item_id)) throw new \RuntimeException('Belum ada item, update kode terlebih dahulu.');
            if ($produksi->transaction_id > 0 || $produksi->detail_id > 0 || ! empty($produksi->invoice)) throw new \RuntimeException('Sudah masuk invoice/gudang');
            if ($produksi->status == Produksi::STATUS_GUDANG) throw new \RuntimeException('Status sudah gudang');

            $warehouse = $this->produksiSettings->resolveWarehouse();
            if (! $warehouse) throw new \RuntimeException('Gudang tujuan tidak ditemukan. Atur produksi.default_warehouse_id di System Settings.');

            $transaction = Transaction::where('invoice_number', $invoice)->where('type', TransactionType::Production->value)->first();
            if (! $transaction) {
                $transaction = Transaction::create(['date' => now()->toDateString(), 'type' => TransactionType::Production->value, 'receiver_id' => $warehouse->id, 'receiver_type' => AddrbookType::Warehouse->value, 'invoice_number' => $invoice, 'user_id' => $userId, 'total_items' => 0]);
            }

            $detail = TransactionDetail::create(['transaction_id' => $transaction->id, 'item_id' => $produksi->item_id, 'quantity' => $produksi->quantity, 'price' => 0, 'discount' => 0, 'total' => 0]);

            $produksi->update(['status' => Produksi::STATUS_GUDANG, 'transaction_id' => $transaction->id, 'detail_id' => $detail->id, 'invoice' => $invoice, 'gudang_date' => now()->toDateString()]);
            $transaction->increment('total_items', $produksi->quantity);
            $this->inventoryService->add($warehouse->id, $produksi->item, $produksi->quantity);
        });
    }
}
