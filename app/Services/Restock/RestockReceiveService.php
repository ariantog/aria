<?php

namespace App\Services\Restock;

use App\Enums\AddrbookType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\RestockCell;
use App\Models\RestockCellHistory;
use App\Models\RestockSheet;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BookClosingService;
use App\Services\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RestockReceiveService
{
    public function __construct(
        protected RestockSettingsService $settings,
        protected TransactionService $transactionService,
        protected BookClosingService $bookClosingService,
    ) {}

    /**
     * @param  list<array{id: int, qty?: int}>  $cells
     */
    public function receive(
        RestockSheet $sheet,
        array $cells,
        User $user,
        string $date,
        ?string $invoiceNumber = null,
    ): Transaction {
        if ($cells === []) {
            throw new InvalidArgumentException('Select at least one row to receive.');
        }

        $this->bookClosingService->validateDate($date);

        $parties = $this->settings->resolveReceiveParties();

        return DB::transaction(function () use ($sheet, $cells, $user, $date, $invoiceNumber, $parties) {
            $cellIds = collect($cells)->pluck('id')->all();
            $qtyById = collect($cells)->keyBy('id')->map(
                fn (array $row) => array_key_exists('qty', $row) ? (int) $row['qty'] : null
            );

            $models = RestockCell::query()
                ->where('restock_sheet_id', $sheet->id)
                ->whereIn('id', $cellIds)
                ->with('item')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($models->count() !== count($cellIds)) {
                throw new InvalidArgumentException('One or more cells do not belong to this sheet.');
            }

            $lineItems = [];

            foreach ($cellIds as $cellId) {
                $cell = $models->get($cellId);
                if (! $cell || ! $cell->item) {
                    throw new InvalidArgumentException('Each received cell must be linked to an item.');
                }

                $shippedBefore = (int) $cell->qty_shipped;
                if ($shippedBefore <= 0) {
                    continue;
                }

                $requested = $qtyById->get($cellId);
                $receivedQty = $requested === null
                    ? $shippedBefore
                    : max(0, $requested);

                if ($receivedQty <= 0) {
                    continue;
                }

                $shortfall = max(0, $shippedBefore - $receivedQty);

                $lineItems[] = [
                    'cell' => $cell,
                    'item_id' => $cell->item_id,
                    'quantity' => $receivedQty,
                    'price' => (float) ($cell->item->cost ?: $cell->item->price ?: 0),
                    'shipped_before' => $shippedBefore,
                    'shortfall' => $shortfall,
                ];
            }

            if ($lineItems === []) {
                throw new InvalidArgumentException('Nothing to receive — selected cells have no shipped quantity.');
            }

            $transaction = Transaction::create([
                'date' => $date,
                'type' => TransactionType::Buy->value,
                'sender_type' => $this->addrbookTypeValue($parties['supplier']),
                'sender_id' => $parties['supplier']->id,
                'receiver_type' => $this->addrbookTypeValue($parties['receiver']),
                'receiver_id' => $parties['receiver']->id,
                'notes' => 'Restock receive',
                'user_id' => $user->id,
                'status' => TransactionStatus::Completed->value,
                'total_items' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                'invoice' => $invoiceNumber ?: null,
            ]);

            if (empty($transaction->invoice)) {
                $transaction->update(['invoice' => (string) $transaction->id]);
            }

            $itemsTotal = 0;
            $totalItems = 0;

            foreach ($lineItems as $line) {
                /** @var RestockCell $cell */
                $cell = $line['cell'];
                $receivedQty = (int) $line['quantity'];
                $shippedBefore = (int) $line['shipped_before'];
                $shortfall = (int) $line['shortfall'];
                $price = (float) $line['price'];
                $total = $receivedQty * $price;

                $transaction->details()->create([
                    'item_id' => $line['item_id'],
                    'date' => $date,
                    'transaction_type' => TransactionType::Buy->value,
                    'sender_id' => $parties['supplier']->id,
                    'receiver_id' => $parties['receiver']->id,
                    'quantity' => $receivedQty,
                    'price' => $price,
                    'discount' => 0,
                    'total' => $total,
                    'notes' => 'Restock receive',
                ]);

                $missingBefore = (int) $cell->qty_missing;
                $cell->qty_shipped = 0;
                if ($shortfall > 0) {
                    $cell->qty_missing = $missingBefore + $shortfall;
                    $cell->missing_at = now();
                }
                $cell->save();

                RestockCellHistory::create([
                    'restock_cell_id' => $cell->id,
                    'field' => 'shipped',
                    'qty_before' => $shippedBefore,
                    'qty_after' => 0,
                    'action' => 'receive',
                    'user_id' => $user->id,
                    'transaction_id' => $transaction->id,
                    'note' => $invoiceNumber,
                ]);

                if ($shortfall > 0) {
                    RestockCellHistory::create([
                        'restock_cell_id' => $cell->id,
                        'field' => 'missing',
                        'qty_before' => $missingBefore,
                        'qty_after' => $missingBefore + $shortfall,
                        'action' => 'missing',
                        'user_id' => $user->id,
                        'transaction_id' => $transaction->id,
                        'note' => 'Receive shortfall',
                    ]);
                }

                $itemsTotal += $total;
                $totalItems += $receivedQty;
            }

            $transaction->update([
                'total' => $itemsTotal,
                'total_items' => $totalItems,
            ]);

            $this->transactionService->handleTransaction($transaction->fresh('details'));

            $sheet->update([
                'last_saved_at' => now(),
                'last_saved_by' => $user->id,
            ]);

            return $transaction->fresh(['details', 'sender', 'receiver']);
        });
    }

    protected function addrbookTypeValue(\App\Models\Addrbook $addrbook): int
    {
        return $addrbook->type instanceof AddrbookType
            ? $addrbook->type->value
            : (int) $addrbook->type;
    }
}
