<?php

namespace App\Actions\Transactions;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use App\Services\BookClosingService;
use App\Services\FixedAssetService;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordFixedAssetBuy
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly FixedAssetService $fixedAssets,
        private readonly BookClosingService $bookClosing,
    ) {}

    /**
     * @param  array{
     *     date: string,
     *     supplier_id: int,
     *     warehouse_id: int,
     *     buy_price: float|int|string,
     *     invoice?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function execute(Item $item, array $data): Transaction
    {
        $this->fixedAssets->assertAssetTetap($item);

        $row = $item->depreciation;
        if (! $row) {
            throw ValidationException::withMessages([
                'item' => ['Asset tetap register row is missing.'],
            ]);
        }

        if ($row->hasBuyTransaction()) {
            throw ValidationException::withMessages([
                'buy_price' => ['Pembelian untuk asset ini sudah dicatat.'],
            ]);
        }

        $this->bookClosing->validateDate($data['date']);

        $supplier = Addrbook::query()->find($data['supplier_id']);
        if (! $supplier || (int) $supplier->type !== Addrbook::TYPE_SUPPLIER) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Supplier is invalid.'],
            ]);
        }

        $warehouse = $this->fixedAssets->assertWarehouse((int) $data['warehouse_id']);
        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => ['Warehouse is required.'],
            ]);
        }

        $price = round((float) $data['buy_price'], 2);
        if ($price < 0.01) {
            throw ValidationException::withMessages([
                'buy_price' => ['Harga perolehan harus lebih dari 0.'],
            ]);
        }

        if ($price < (float) $row->residual_value) {
            throw ValidationException::withMessages([
                'buy_price' => ['Harga perolehan tidak boleh lebih kecil dari nilai residu.'],
            ]);
        }

        return DB::transaction(function () use ($item, $row, $data, $supplier, $warehouse, $price) {
            $signed = Transaction::signedAmount(Transaction::TYPE_BUY, $price);
            $buyDate = Carbon::parse($data['date']);
            $life = $this->fixedAssets->resolveUsefulLifeMonths($row);

            $trx = Transaction::create([
                'date' => $buyDate->toDateString(),
                'type' => Transaction::TYPE_BUY,
                'sender_type' => (int) $supplier->type,
                'sender_id' => $supplier->id,
                'receiver_type' => (int) $warehouse->type,
                'receiver_id' => $warehouse->id,
                'invoice' => ! empty($data['invoice']) ? $data['invoice'] : null,
                'notes' => $data['notes'] ?? ('Pembelian asset tetap '.$item->name),
                'user_id' => Auth::id(),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => $signed,
                'real_total' => $signed,
                'total_items' => 1,
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            if (empty($trx->invoice)) {
                $trx->update(['invoice' => (string) $trx->id]);
            }

            $trx->details()->create([
                'item_id' => $item->id,
                'date' => $trx->date,
                'transaction_type' => Transaction::TYPE_BUY,
                'sender_id' => $trx->sender_id,
                'receiver_id' => $trx->receiver_id,
                'quantity' => 1,
                'price' => $price,
                'discount' => 0,
                'total' => $price,
                'notes' => null,
            ]);

            $this->transactionService->handleTransaction($trx->fresh('details'));

            $item->update([
                'cost' => $price,
                'price' => $price,
            ]);

            $row->buy_date = $buyDate->toDateString();
            $row->buy_price = $price;
            $row->buy_transaction_id = $trx->id;
            $row->warehouse_id = $warehouse->id;
            $row->expire_date = $this->fixedAssets->expireDate($buyDate, $life);
            $row->save();

            return $trx->fresh(['details', 'sender', 'receiver']);
        });
    }
}
