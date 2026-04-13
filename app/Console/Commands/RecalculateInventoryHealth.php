<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\DailyInventorySummary;
use App\Models\Transaction;
use App\Models\WarehouseItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateInventoryHealth extends Command
{
    protected $signature = 'app:recalculate-inventory-health';

    protected $description = 'Recalculate daily inventory summaries for stock health and rebalancing';

    public function handle()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $this->info('Starting recalculation of Daily Inventory Summaries...');

        // Clear existing data
        DailyInventorySummary::truncate();

        // 1. Process Sender Side
        $this->info('Processing warehouse as sender...');
        $this->insertSummaries('sender');

        // 2. Process Receiver Side (Updating existing or inserting new)
        $this->info('Processing warehouse as receiver...');
        $this->updateSummaries('receiver');

        // 3. Snapshot current stock
        $this->info('Recording current stock snapshots...');
        WarehouseItem::chunk(1000, function ($stocks) {
            foreach ($stocks as $stock) {
                DB::table('daily_inventory_summaries')->updateOrInsert(
                    ['date' => now()->toDateString(), 'warehouse_id' => $stock->warehouse_id, 'item_id' => $stock->item_id],
                    ['stock_on_hand' => $stock->quantity, 'updated_at' => now()]
                );
            }
        });

        $this->info('Recalculation completed successfully.');
    }

    protected function insertSummaries($side)
    {
        $typeSell = Transaction::TYPE_SELL;
        $typeBuy = Transaction::TYPE_BUY;
        $typeMove = Transaction::TYPE_MOVE;
        $typeReturn = Transaction::TYPE_RETURN;
        $typeReturnSup = Transaction::TYPE_RETURN_SUPPLIER;
        $typeAdjust = Transaction::TYPE_ADJUST;
        $whType = Addrbook::TYPE_WAREHOUSE;

        $sql = "
            INSERT INTO daily_inventory_summaries 
            (date, warehouse_id, item_id, qty_sell, qty_buy, qty_move_in, qty_move_out, qty_return_in, qty_return_out, qty_adjust_in, qty_adjust_out, created_at, updated_at)
            SELECT 
                t.date,
                t.{$side}_id,
                td.item_id,
                SUM(CASE WHEN t.type = $typeSell THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeBuy THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeMove AND '$side' = 'receiver' THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeMove AND '$side' = 'sender' THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeReturn THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeReturnSup THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeAdjust AND td.quantity > 0 THEN td.quantity ELSE 0 END),
                SUM(CASE WHEN t.type = $typeAdjust AND td.quantity < 0 THEN ABS(td.quantity) ELSE 0 END),
                NOW(),
                NOW()
            FROM transaction_details td
            JOIN transactions t ON td.transaction_id = t.id
            WHERE t.{$side}_type = $whType
            GROUP BY t.date, t.{$side}_id, td.item_id
        ";

        DB::statement($sql);
    }

    protected function updateSummaries($side)
    {
        $typeSell = Transaction::TYPE_SELL;
        $typeBuy = Transaction::TYPE_BUY;
        $typeMove = Transaction::TYPE_MOVE;
        $typeReturn = Transaction::TYPE_RETURN;
        $typeReturnSup = Transaction::TYPE_RETURN_SUPPLIER;
        $typeAdjust = Transaction::TYPE_ADJUST;
        $whType = Addrbook::TYPE_WAREHOUSE;

        $query = DB::table('transaction_details as td')
            ->join('transactions as t', 'td.transaction_id', '=', 't.id')
            ->where("t.{$side}_type", $whType)
            ->selectRaw("
                t.date,
                t.{$side}_id as warehouse_id,
                td.item_id,
                SUM(CASE WHEN t.type = $typeSell THEN td.quantity ELSE 0 END) as qty_sell,
                SUM(CASE WHEN t.type = $typeBuy THEN td.quantity ELSE 0 END) as qty_buy,
                SUM(CASE WHEN t.type = $typeMove AND '$side' = 'receiver' THEN td.quantity ELSE 0 END) as qty_move_in,
                SUM(CASE WHEN t.type = $typeMove AND '$side' = 'sender' THEN td.quantity ELSE 0 END) as qty_move_out,
                SUM(CASE WHEN t.type = $typeReturn THEN td.quantity ELSE 0 END) as qty_return_in,
                SUM(CASE WHEN t.type = $typeReturnSup THEN td.quantity ELSE 0 END) as qty_return_out,
                SUM(CASE WHEN t.type = $typeAdjust AND td.quantity > 0 THEN td.quantity ELSE 0 END) as qty_adjust_in,
                SUM(CASE WHEN t.type = $typeAdjust AND td.quantity < 0 THEN ABS(td.quantity) ELSE 0 END) as qty_adjust_out
            ")
            ->groupBy('t.date', "t.{$side}_id", 'td.item_id')
            ->orderBy('t.date')
            ->orderBy("t.{$side}_id")
            ->orderBy('td.item_id');

        $query->chunk(500, function ($results) {
            foreach ($results as $row) {
                $existing = DB::table('daily_inventory_summaries')
                    ->where(['date' => $row->date, 'warehouse_id' => $row->warehouse_id, 'item_id' => $row->item_id])
                    ->first();

                if ($existing) {
                    DB::table('daily_inventory_summaries')
                        ->where('id', $existing->id)
                        ->update([
                            'qty_sell' => $existing->qty_sell + $row->qty_sell,
                            'qty_buy' => $existing->qty_buy + $row->qty_buy,
                            'qty_move_in' => $existing->qty_move_in + $row->qty_move_in,
                            'qty_move_out' => $existing->qty_move_out + $row->qty_move_out,
                            'qty_return_in' => $existing->qty_return_in + $row->qty_return_in,
                            'qty_return_out' => $existing->qty_return_out + $row->qty_return_out,
                            'qty_adjust_in' => $existing->qty_adjust_in + $row->qty_adjust_in,
                            'qty_adjust_out' => $existing->qty_adjust_out + $row->qty_adjust_out,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('daily_inventory_summaries')->insert([
                        'date' => $row->date,
                        'warehouse_id' => $row->warehouse_id,
                        'item_id' => $row->item_id,
                        'qty_sell' => $row->qty_sell,
                        'qty_buy' => $row->qty_buy,
                        'qty_move_in' => $row->qty_move_in,
                        'qty_move_out' => $row->qty_move_out,
                        'qty_return_in' => $row->qty_return_in,
                        'qty_return_out' => $row->qty_return_out,
                        'qty_adjust_in' => $row->qty_adjust_in,
                        'qty_adjust_out' => $row->qty_adjust_out,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
