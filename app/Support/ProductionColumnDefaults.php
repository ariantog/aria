<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Fill legacy production NOT NULL columns that L12 does not set on create.
 *
 * Derived from database/old.sql vs database/new.sql — both schemas omit DEFAULT on
 * many legacy columns; MySQL strict mode then rejects partial inserts.
 */
class ProductionColumnDefaults
{
    /** @var array<string, array<string, mixed>> */
    private const TABLE_DEFAULTS = [
        'customers' => [
            'description' => '',
            'phone' => '',
            'phone2' => '',
            'email' => '',
            'fax' => '',
            'discount' => 0,
            'return_p' => 0,
            'parent_id' => 0,
            'memberId' => '',
            'password' => '',
            'portalId' => 0,
        ],
        'customer_class' => [
            'class' => '',
            'adjust' => 0,
            'depreciation' => 0,
            'rating' => 0,
            'cash_in' => 0,
            'cash_out' => 0,
            'sell' => 0,
            'buy' => 0,
            'return' => 0,
            'return_supplier' => 0,
            'use' => 0,
            'move' => 0,
            'transfer' => 0,
        ],
        'customerstat' => [
            'balance' => 0,
        ],
        'items' => [
            'tag_ids' => '',
            'description' => '',
            'description2' => '',
            'variant' => '',
            'pcode' => '',
        ],
        'item_group' => [
            'master' => '',
            'variant' => '',
            'description' => '',
            'alias' => '',
            'description2' => '',
        ],
        'transactions' => [
            'description' => '',
            'detail_ids' => '',
            'cogs' => 0,
            'location_id' => 0,
        ],
        'transaction_details' => [
            'transaction_disc' => 0,
        ],
        'deleted' => [
            'invoice' => '',
            'description' => '',
            'detail_ids' => '',
            'cogs' => 0,
            'location_id' => 0,
            'ppn' => 0,
            'submit_a_count' => 0,
            'submit_b_count' => 0,
            'sync_hide' => 'N',
            'sender_type' => 0,
            'receiver_type' => 0,
        ],
        'deleted_details' => [
            'transaction_disc' => 0,
        ],
        'settings' => [
            'location_id' => 0,
            'value' => '',
        ],
        'prod_produksi' => [
            'item_id' => 0,
            'jahit_id' => 0,
            'customer' => '',
            'potong_id' => 0,
            'warna' => '',
            'temp_name' => '',
            'description' => '',
            'size_id' => 0,
            'invoice' => '',
            'detail_id' => 0,
            'surat_jalan_potong' => '',
        ],
        'operations' => [
            'description' => '',
        ],
        'tags' => [
            'price' => 0,
        ],
    ];

    public static function apply(Model $model): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        self::applyTransactionDetailFromParent($model);

        $defaults = self::TABLE_DEFAULTS[$model->getTable()] ?? [];
        foreach ($defaults as $column => $value) {
            if (! Schema::hasColumn($model->getTable(), $column)) {
                continue;
            }

            if ($model->{$column} === null) {
                $model->{$column} = $value;
            }
        }
    }

    private static function applyTransactionDetailFromParent(Model $model): void
    {
        if ($model->getTable() !== 'transaction_details' || ! $model->transaction_id) {
            return;
        }

        $transaction = $model->relationLoaded('transaction')
            ? $model->transaction
            : Transaction::query()->find($model->transaction_id);

        if (! $transaction) {
            return;
        }

        $table = $model->getTable();

        if (Schema::hasColumn($table, 'date') && $model->date === null) {
            $model->date = $transaction->date;
        }

        if (Schema::hasColumn($table, 'transaction_type') && $model->transaction_type === null) {
            $type = $transaction->type;
            $model->transaction_type = $type instanceof TransactionType ? $type->value : $type;
        }

        if (Schema::hasColumn($table, 'sender_id') && $model->sender_id === null) {
            $model->sender_id = $transaction->sender_id ?? 0;
        }

        if (Schema::hasColumn($table, 'receiver_id') && $model->receiver_id === null) {
            $model->receiver_id = $transaction->receiver_id ?? 0;
        }
    }
}
