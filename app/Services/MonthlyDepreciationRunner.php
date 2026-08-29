<?php

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Depreciation;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Support\SettingRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MonthlyDepreciationRunner
{
    public function __construct(
        private readonly FixedAssetService $fixedAssets,
        private readonly TransactionService $transactionService,
        private readonly BookClosingService $bookClosing,
    ) {}

    /**
     * @return list<array{item: Item, register: Depreciation, amount: float, remaining: float, monthly: float}>
     */
    public function preview(Carbon $month): array
    {
        $lines = [];

        foreach ($this->activeRegisters() as $row) {
            $charge = $this->fixedAssets->chargeForMonth($row, $month);
            if ($charge === null) {
                continue;
            }

            $lines[] = [
                'item' => $row->item,
                'register' => $row,
                'amount' => $charge['amount'],
                'remaining' => $charge['remaining'],
                'monthly' => $charge['monthly'],
            ];
        }

        return $lines;
    }

    /**
     * @return array{transaction: Transaction|null, posted: int, skipped: int}
     */
    public function run(Carbon $month, int $expenseAccountId, int $contraAccountId, ?int $userId = null): array
    {
        $this->bookClosing->validateDate($month->copy()->endOfMonth()->toDateString());

        $expense = $this->fixedAssets->assertAccount($expenseAccountId, 'expense_account_id');
        $contra = $this->fixedAssets->assertAccount($contraAccountId, 'contra_account_id');

        if ($expense->id === $contra->id) {
            throw ValidationException::withMessages([
                'contra_account_id' => ['Akun beban dan akumulasi penyusutan harus berbeda.'],
            ]);
        }

        $lines = $this->preview($month);
        if ($lines === []) {
            return ['transaction' => null, 'posted' => 0, 'skipped' => 0];
        }

        $date = $month->copy()->endOfMonth()->toDateString();
        $invoice = $this->nextInvoice($month);
        $notes = 'Penyusutan '.$month->format('Y-m');
        $total = round(array_sum(array_column($lines, 'amount')), 2);
        $signed = Transaction::signedAmount(Transaction::TYPE_DEPRECIATION, $total);

        $transaction = DB::transaction(function () use ($lines, $expense, $contra, $date, $invoice, $notes, $signed, $userId) {
            $trx = Transaction::create([
                'date' => $date,
                'type' => Transaction::TYPE_DEPRECIATION,
                'sender_type' => (int) $expense->type,
                'sender_id' => $expense->id,
                'receiver_type' => (int) $contra->type,
                'receiver_id' => $contra->id,
                'invoice' => $invoice,
                'notes' => $notes,
                'user_id' => $userId ?? Auth::id() ?? User::query()->orderBy('id')->value('id'),
                'status' => Transaction::STATUS_COMPLETED,
                'total' => $signed,
                'real_total' => $signed,
                'total_items' => count($lines),
                'adjustment' => 0,
                'discount' => 0,
                'ppn' => 0,
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            ]);

            foreach ($lines as $line) {
                $trx->details()->create([
                    'item_id' => $line['item']->id,
                    'date' => $trx->date,
                    'transaction_type' => Transaction::TYPE_DEPRECIATION,
                    'sender_id' => $trx->sender_id,
                    'receiver_id' => $trx->receiver_id,
                    'quantity' => 1,
                    'price' => $line['amount'],
                    'discount' => 0,
                    'total' => $line['amount'],
                    'notes' => null,
                ]);
            }

            $this->transactionService->handleTransaction($trx->fresh('details'));

            return $trx->fresh(['details', 'sender', 'receiver']);
        });

        $this->persistAccountSettings($expenseAccountId, $contraAccountId);

        return [
            'transaction' => $transaction,
            'posted' => count($lines),
            'skipped' => 0,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Depreciation>
     */
    private function activeRegisters()
    {
        return Depreciation::query()
            ->where('buy_price', '>', 0)
            ->where('buy_transaction_id', '>', 0)
            ->whereHas('item', fn ($query) => $query->where('type', ItemType::ASSET_TETAP))
            ->with('item')
            ->orderBy('item_id')
            ->get();
    }

    private function nextInvoice(Carbon $month): string
    {
        $base = 'DEP-'.$month->format('Y-m');
        $invoice = $base;
        $suffix = 2;

        while (Transaction::query()
            ->where('type', Transaction::TYPE_DEPRECIATION)
            ->where('invoice', $invoice)
            ->exists()) {
            $invoice = $base.'-'.$suffix;
            $suffix++;
        }

        return $invoice;
    }

    private function persistAccountSettings(int $expenseAccountId, int $contraAccountId): void
    {
        foreach ([
            FixedAssetService::SETTING_EXPENSE_ACCOUNT => $expenseAccountId,
            FixedAssetService::SETTING_CONTRA_ACCOUNT => $contraAccountId,
        ] as $slug => $value) {
            $definition = SettingRegistry::definition($slug) ?? [
                'group' => 'Accounting',
                'name' => $slug,
            ];

            $attributes = [
                'group' => $definition['group'],
                'name' => $definition['name'],
                'value' => $value,
            ];

            if (Schema::hasColumn((new Setting)->getTable(), 'location_id')) {
                $attributes['location_id'] = 0;
            }

            Setting::query()->updateOrCreate(['slug' => $slug], $attributes);
        }
    }
}
