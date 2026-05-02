<?php

namespace App\Console\Commands;

use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderJubelioToAria extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jubelio:order-jubelio-to-aria {--all : Process all pending orders} {--truncate : Delete existing transactions and reset all orders to pending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proses order jubelio ke aria transaction';

    /**
     * Execute the console command.
     */
    public function handle(TransactionService $transactionService)
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
            $query->orderBy('id', 'asc')->chunkById(500, function ($orders) use ($transactionService) {
                foreach ($orders as $logjubelio) {
                    $this->processOrder($logjubelio, $transactionService);
                }
            });
        } else {
            // Get 1 terlama (oldest) based on created_at for cron processing
            $logjubelio = $query->orderBy('created_at', 'asc')->first();

            if (! $logjubelio) {
                Log::info('Tidak ada antrian order Jubelio untuk diproses.');

                return;
            }

            $this->processOrder($logjubelio, $transactionService);
        }

        $this->info('Process completed.');
    }

    /**
     * Process a single Jubelio order record.
     */
    protected function processOrder(Jubelioorder $logjubelio, TransactionService $transactionService)
    {
        Log::info("Memproses Jubelioorder ID: {$logjubelio->id}, Type: {$logjubelio->type}, Invoice: {$logjubelio->invoice}");

        $runCount = $logjubelio->run_count + 1;
        $dataApi = json_decode($logjubelio->payload, true);

        if (! $dataApi) {
            $logjubelio->update([
                'run_count' => $runCount,
                'error_type' => 3,
                'error' => 'Payload JSON tidak valid',
                'status' => 1,
            ]);

            return;
        }

        if ($logjubelio->type == 'SELL') {
            $arrayStoreId = $dataApi['store_id'];
            $arrayLocationId = $dataApi['location_id'];
            $arrayItems = $dataApi['items'];
            $arrayInvoice = $dataApi['salesorder_no'];
            $arraySubTotal = $dataApi['sub_total'];
            $arrayGrandTotal = $dataApi['grand_total'];

            $jubelioSync = Jubeliosync::where('jubelio_store_id', $arrayStoreId)
                ->where('jubelio_location_id', $arrayLocationId)
                ->first();

            if ($jubelioSync) {
                $itemCodes = collect($arrayItems)->pluck('item_code')->map(fn ($code) => strtoupper($code))->unique();

                $existingProducts = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
                    ->get(['id', 'code', 'name'])
                    ->keyBy(fn ($item) => strtoupper($item->code));

                $groupedData = collect($arrayItems)->partition(fn ($item) => isset($existingProducts[strtoupper($item['item_code'])]));

                $matched = $groupedData[0]->map(fn ($item) => [
                    'itemId' => $existingProducts[strtoupper($item['item_code'])]->id,
                    'code' => $existingProducts[strtoupper($item['item_code'])]->code,
                    'name' => $existingProducts[strtoupper($item['item_code'])]->name,
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'discount' => 0,
                    'subtotal' => $item['qty'] * $item['price'],
                ])->values();

                $notMatched = $groupedData[1];

                if ($notMatched->count() > 0) {
                    $logjubelio->update([
                        'run_count' => $runCount,
                        'error_type' => 1,
                        'error' => 'SKU tidak ditemukan: '.implode(', ', $notMatched->pluck('item_code')->toArray()),
                        'status' => 1,
                    ]);
                } else {
                    $cekTransaksi = Transaction::where('type', Transaction::TYPE_SELL)
                        ->where('invoice_number', $arrayInvoice)
                        ->first();

                    if ($cekTransaksi) {
                        $logjubelio->update([
                            'run_count' => $runCount,
                            'error_type' => 2,
                            'error' => 'Transaction sudah ada',
                            'status' => 2,
                        ]);
                    } else {
                        $adjust = $arraySubTotal - $arrayGrandTotal;
                        $dataJubelio = [
                            'date' => $dataApi['transaction_date'] ?? now()->toDateString(),
                            'warehouse' => $jubelioSync->warehouse_id,
                            'customer' => $jubelioSync->customer_id,
                            'invoice' => $arrayInvoice,
                            'note' => 'generated by cron aria',
                            'account' => '7204',
                            'addMoreInputFields' => $matched,
                            'disc' => '0',
                            'adjustment' => $this->toggleSign($adjust),
                            'ongkir' => '0',
                        ];

                        $createData = $this->createTransaction(Transaction::TYPE_SELL, (object) $dataJubelio, $transactionService);

                        if ($createData['status'] == '200') {
                            $logjubelio->update([
                                'run_count' => $runCount,
                                'error_type' => 10,
                                'status' => 2,
                                'execute_by' => 0,
                            ]);
                            Log::info("Berhasil Sell: $arrayInvoice");
                        } else {
                            $logjubelio->update([
                                'run_count' => $runCount,
                                'error_type' => 1,
                                'error' => $createData['message'],
                                'status' => 1,
                            ]);
                        }
                    }
                }
            } else {
                $logjubelio->update([
                    'run_count' => $runCount,
                    'error_type' => 1,
                    'error' => 'Data sync store/location ID tidak ditemukan',
                    'status' => 1,
                ]);
            }
        } elseif ($logjubelio->type == 'RETURN') {
            $cekTransaksiSell = Transaction::where('type', Transaction::TYPE_SELL)
                ->where('invoice_number', $dataApi['salesorder_no'])
                ->first();

            if ($cekTransaksiSell) {
                $itemCodes = collect($dataApi['items'])->pluck('item_code')->map(fn ($c) => strtoupper($c))->unique();

                $existingProducts = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
                    ->get(['id', 'code', 'name'])
                    ->keyBy(fn ($item) => strtoupper($item->code));

                $groupedData = collect($dataApi['items'])->partition(fn ($item) => isset($existingProducts[strtoupper($item['item_code'])]));

                $matched = $groupedData[0]->map(fn ($item) => [
                    'itemId' => $existingProducts[strtoupper($item['item_code'])]->id,
                    'code' => $existingProducts[strtoupper($item['item_code'])]->code,
                    'name' => $existingProducts[strtoupper($item['item_code'])]->name,
                    'quantity' => $item['qty_in_base'],
                    'price' => $item['price'],
                    'discount' => 0,
                    'subtotal' => $item['qty_in_base'] * $item['price'],
                ])->values();

                $notMatched = $groupedData[1];

                if ($notMatched->count() > 0) {
                    $logjubelio->update([
                        'run_count' => $runCount,
                        'error_type' => 1,
                        'error' => 'SKU tidak ditemukan: '.implode(', ', $notMatched->pluck('item_code')->toArray()),
                        'status' => 1,
                    ]);
                } else {
                    $cekTransaksi = Transaction::where('type', Transaction::TYPE_RETURN)
                        ->where('invoice_number', $dataApi['return_no'])
                        ->first();

                    if ($cekTransaksi) {
                        $logjubelio->update([
                            'run_count' => $runCount,
                            'error_type' => 2,
                            'error' => 'Invoice Retur sudah ada',
                            'status' => 2,
                        ]);
                    } else {
                        $jubelioSync = Jubeliosync::where('jubelio_store_id', $dataApi['store_id'])
                            ->where('jubelio_location_id', $dataApi['location_id'])
                            ->first();

                        if ($jubelioSync) {
                            $adjust = $dataApi['sub_total'] - $dataApi['grand_total'];
                            $dataJubelio = [
                                'date' => $dataApi['transaction_date'] ?? now()->toDateString(),
                                'warehouse' => $jubelioSync->warehouse_id,
                                'customer' => $jubelioSync->customer_id,
                                'invoice' => $dataApi['return_no'],
                                'description' => $dataApi['salesorder_no'],
                                'note' => 'generated by jubelio',
                                'account' => '7204',
                                'addMoreInputFields' => $matched,
                                'disc' => '0',
                                'adjustment' => $this->toggleSign($adjust),
                                'ongkir' => '0',
                            ];

                            $createData = $this->createTransaction(Transaction::TYPE_RETURN, (object) $dataJubelio, $transactionService);

                            if ($createData['status'] == '200') {
                                $logjubelio->update([
                                    'run_count' => $runCount,
                                    'error_type' => 10,
                                    'status' => 2,
                                    'execute_by' => 0,
                                ]);
                                Log::info('Berhasil Return: '.$dataApi['return_no']);
                            } else {
                                $logjubelio->update([
                                    'run_count' => $runCount,
                                    'error_type' => 1,
                                    'error' => $createData['message'],
                                    'status' => 1,
                                ]);
                            }
                        } else {
                            $logjubelio->update([
                                'run_count' => $runCount,
                                'error_type' => 1,
                                'error' => 'Data sync store/location ID untuk return tidak ditemukan',
                                'status' => 1,
                            ]);
                        }
                    }
                }
            } else {
                $logjubelio->update([
                    'run_count' => $runCount,
                    'error_type' => 3,
                    'error' => 'Transaksi jual (asal) tidak ditemukan untuk retur ini',
                    'status' => 1,
                ]);
            }
        }
    }

    protected function toggleSign($value)
    {
        return -$value;
    }

    protected function createTransaction($type, $dataJubelio, $transactionService)
    {
        try {
            return DB::transaction(function () use ($type, $dataJubelio, $transactionService) {
                $customer = Addrbook::find($dataJubelio->customer);
                $warehouse = Addrbook::find($dataJubelio->warehouse);

                if (! $customer || ! $warehouse) {
                    throw new \Exception('Customer or Warehouse not found.');
                }

                $transaction = new Transaction;
                $transaction->date = Carbon::parse($dataJubelio->date);
                $transaction->type = $type;
                $transaction->adjustment = $dataJubelio->adjustment;
                $transaction->user_id = null; // Set to null for cron entries
                $transaction->submit_type = 2; // Jubelio
                $transaction->description = $dataJubelio->description ?? '';
                $transaction->notes = $dataJubelio->note ?? '';
                $transaction->invoice_number = $dataJubelio->invoice;
                $transaction->due_date = null; // Set to null as requested
                $transaction->status = Transaction::STATUS_COMPLETED;

                // Set sender/receiver based on type
                switch ($type) {
                    case Transaction::TYPE_RETURN:
                        $transaction->sender_id = $customer->id;
                        $transaction->sender_type = $customer->type;
                        $transaction->receiver_id = $warehouse->id;
                        $transaction->receiver_type = $warehouse->type;
                        break;
                    case Transaction::TYPE_SELL:
                        $transaction->sender_id = $warehouse->id;
                        $transaction->sender_type = $warehouse->type;
                        $transaction->receiver_id = $customer->id;
                        $transaction->receiver_type = $customer->type;
                        break;
                }

                $transaction->save();

                $totalQty = 0;
                $subTotal = 0;

                foreach ($dataJubelio->addMoreInputFields as $item) {
                    TransactionDetail::create([
                        'transaction_id' => $transaction->id,
                        'date' => $transaction->date,
                        'transaction_type' => $type,
                        'sender_id' => $transaction->sender_id,
                        'receiver_id' => $transaction->receiver_id,
                        'item_id' => $item['itemId'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'discount' => 0,
                        'total' => $item['subtotal'],
                    ]);
                    $totalQty += $item['quantity'];
                    $subTotal += $item['subtotal'];
                }

                $transaction->total = $subTotal;
                $grandTotal = $subTotal + $transaction->adjustment;

                // Balance logic: SELL is negative
                $transaction->grand_total = ($type === Transaction::TYPE_SELL) ? -$grandTotal : $grandTotal;
                $transaction->total_items = $totalQty;
                $transaction->save();

                // Handle stock and balances using Service
                $transactionService->handleTransaction($transaction);

                return [
                    'status' => '200',
                    'message' => 'ok',
                    'transaction_id' => $transaction->id,
                ];
            });
        } catch (\Exception $e) {
            return [
                'status' => '422',
                'message' => $e->getMessage(),
            ];
        }
    }
}
