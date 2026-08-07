<?php

namespace App\Http\Controllers;

use App\Exceptions\ModelException;
use App\Helpers\InvoiceTrackerHelpers;
use App\Helpers\StatManagerHelper;
use App\Models\Cronrun;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioController extends Controller
{
    public function cekOrder(Request $request)
    {

        $jsonData = [];
        if ($request->order_id) {

            $token = Cache::get('jubelio_data')['token'] ?? null;


            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'authorization' => $token,
            ])->get('https://api2.jubelio.com/sales/orders/' . $request->order_id);

            if ($response->failed()) {
                return 'Gagal mengambil data order dari API Jubelio. Status: ' . $response->status();
            }

            $data = $response->json();

            $jsonData = $data;
        }

        return view('jubelio.cek.index', compact('jsonData'));
    }

    public function order(Request $request)
    {


        $secret = 'corenation2025';
        $content = trim($request->getContent());
        $sign = hash_hmac('sha256', $content . $secret, $secret, false);
        $signature = $request->header('Sign');

        if ($signature !== $sign) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }


        $dataApi = $request->all();

        // if ($dataApi['source'] === "SHOPEE") {
        //     return response()->json([
        //         'status' => 'ok',
        //         'message' => 'Data dari Shopee tidak diproses',
        //     ], 200);
        // }

        if ($dataApi['status'] === "SHIPPED") {
            $tanggal = Carbon::parse($dataApi['transaction_date']);
            $threshold = Carbon::parse('2025-03-06');

            if ($tanggal->lessThan($threshold)) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Transaksi sebelum tanggal 06 Maret 2025 tidak dibuat. Tanggal transaksi: ' . $tanggal->toDateTimeString(),
                ], 200);
            }
            $destyInvoice = str_replace("SP-", "", $dataApi['salesorder_no']);
            $cekTransaksi = Transaction::where('type', Transaction::TYPE_SELL)->where('invoice', $dataApi['salesorder_no'])->orWhere('invoice', $destyInvoice)->first();

            $exists = Jubelioorder::where('invoice', $dataApi['salesorder_no'])
                ->where('type', 'SELL')
                ->where('order_status', $dataApi['status'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'Data already exists',
                ], 200);
            } else {

                if ($cekTransaksi) {

                    return response()->json([
                        'status' => 'ok',
                        'message' => 'Invoice sudah ada',
                    ], 200);
                } else {

                    DB::table('jubelioorders')->insert([
                        'jubelio_order_id'  => $dataApi['salesorder_id'],
                        'source'            => 1,
                        'invoice'           => $dataApi['salesorder_no'],
                        'type'              => 'SELL',
                        'order_status'      => $dataApi['status'],
                        'run_count'         => 0,
                        'error_type'        => null,
                        'error'             => null,
                        'payload'           => json_encode($dataApi),
                        'execute_by'        => null,
                        'status'            => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    return response()->json([
                        'status' => 'ok',
                        'message' => 'Data saved successfully',
                    ], 200);
                }
            }
        } elseif ($dataApi['status'] === "CANCELED") {
            $transaction = Transaction::where('type', Transaction::TYPE_SELL)
                ->where('invoice', $dataApi['salesorder_no'])
                ->first();

            if ($transaction) {
                if ($transaction->jubelio_return > 0) {
                    return response()->json([
                        'status' => 'ok',
                        'message' => 'Transaksi sudah return',
                    ], 200);
                }

                $returnData = new Jubelioreturn();
                $returnData->order_id       = $dataApi['salesorder_id'];
                $returnData->transaction_id = $transaction->id;
                $returnData->method_pay     = $dataApi['payment_method'];
                $returnData->invoice        = $dataApi['salesorder_no'];
                $returnData->pesan          = $dataApi['cancel_reason_detail'];
                $returnData->location_name  = $dataApi['location_name'];
                $returnData->store_name     = $dataApi['source_name'];
                $returnData->save();

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Data saved successfully',
                ], 200);
            }

            return response()->json([
                'status' => 'ok',
                'message' => 'Transaksi tidak ditemukan',
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Status ' . $dataApi['status'] . ' ok',
        ], 200);
    }

    public function retur(Request $request)
    {
        $secret = 'corenation2025';
        $content = trim($request->getContent());

        $sign = hash_hmac('sha256', $content . $secret, $secret, false);

        $signature = $request->header('Sign');

        if ($signature !== $sign) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $data = $request->all();

        $logStore = [];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'authorization' => Cache::get('jubelio_data')['token'],
        ])
            ->get('https://api2.jubelio.com/sales/sales-returns/' . $data['return_id']);



        if ($response->failed()) {
            $statusCode = $response->status();

            // Tangani kasus jika ID tidak ditemukan (404)
            if ($statusCode === 404) {
                return response()->json([
                    'status' => 'ok',
                    'pesan' => 'Data retur tidak ditemukan.',
                    'logStore' => $logStore
                ], 200);
            }

            return response()->json([
                'status' => 'ok',
                'pesan' => 'Gagal mengambil data dari API. Response: ' . $response->body(),
                'logStore' => $logStore
            ], 200);
        }



        $dataApi = json_decode($response->body(), true);


        $cekTransaksi = Transaction::where('type', Transaction::TYPE_SELL)->where('invoice', $dataApi['salesorder_no'])->first();

        $exists = Jubelioorder::where('invoice', $dataApi['salesorder_no'])
            ->where('type', 'RETURN')
            ->where('order_status', 'RETURN')
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'ok',
                'message' => 'Data already exists',
            ], 200);
        } else {

            if (!$cekTransaksi) {

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Transaksi sell tidak ada.',
                ], 200);
            } else {

                DB::table('jubelioorders')->insert([
                    'jubelio_order_id'  => $dataApi['return_id'],
                    'source'            => 1,
                    'invoice'           => $dataApi['return_no'],
                    'type'              => 'RETURN',
                    'order_status'      => 'RETURN',
                    'run_count'         => 0,
                    'error_type'        => null,
                    'error'             => null,
                    'payload'           => json_encode($dataApi),
                    'execute_by'        => null,
                    'status'            => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                return response()->json([
                    'status' => 'ok',
                    'message' => 'Data saved successfully',
                ], 200);
            }
        }
    }

    public function index(Request $request)
    {
        // Ambil semua statistik dalam 1 kali query ke database
        $stats = Jubelioorder::selectRaw("
        COUNT(CASE WHEN status = 0 THEN 1 END) as pending,
        COUNT(CASE WHEN status = 2 AND error_type = 10 THEN 1 END) as success,
        COUNT(CASE WHEN status = 2 AND error_type = 2 THEN 1 END) as warning,
        COUNT(CASE WHEN status = 1 AND error_type = 1 THEN 1 END) as error
    ")->first();

        $query = Jubelioorder::orderBy('updated_at', 'desc');

        // Pencarian Invoice
        if ($request->invoice) {
            $query->where('invoice', 'like', '%' . $request->invoice . '%');
        }

        // Filter Status
        if ($request->status == 'warning') {
            $query->where('status', 2)->where('error_type', 2);
        } elseif ($request->status == 'success') {
            $query->where('status', 2)->where('error_type', 10);
        } elseif ($request->status == 'error') {
            $query->where('status', 1)->where('error_type', 1);
        } else {
            // Default view: Pending (jika tidak sedang mencari invoice)
            if (!$request->invoice) {
                $query->where('status', 0);
            }
        }

        $dataList = $query->paginate(100)->withQueryString();

        return view('jubelio.webhook.index', compact('dataList', 'stats'));
    }


    public function detail($id)
    {
        $data = Jubelioorder::find($id);

        $jsonData = json_decode($data->payload, true); // pastikan jadi array/objek PHP


        return view('jubelio.webhook.detail', compact('data', 'jsonData'));
    }

    private function getOrder($orderId)
    {
        try {
            $token = Cache::get('jubelio_data')['token'] ?? null;

            if (!$token) {
                throw new \Exception('Token Jubelio tidak tersedia.');
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'authorization' => $token,
            ])->get('https://api2.jubelio.com/sales/orders/' . $orderId);

            if ($response->failed()) {
                throw new \Exception('Gagal mengambil data order dari API Jubelio. Status: ' . $response->status());
            }

            $data = $response->json();

            if (!$data || !isset($data['salesorder_no'])) {
                throw new \Exception('Data order tidak valid atau kosong.');
            }

            return $data;
        } catch (\Exception $e) {

            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function createManual($id)
    {

        $logjubelio = Jubelioorder::findOrFail($id);

        if ($logjubelio->source == 1) {

            $data = json_decode($logjubelio->payload, true);
        } else {
            $data = $this->getOrder($logjubelio->jubelio_order_id);
        }


        // dd($logjubelio);

        //tes

        $adjust = $data['sub_total'] - $data['grand_total'];

        $jubelioSync = Jubeliosync::where('jubelio_store_id', $data['store_id'])->where('jubelio_location_id', $data['location_id'])->first();


        $sid = $id;

        return view('jubelio.webhook.manual', compact('jubelioSync', 'data', 'adjust', 'sid','logjubelio'));
    }

    public function storeManual($id)
    {
        $logjubelio = Jubelioorder::where('id', $id)->first();

        if (!$logjubelio) {
            return redirect()->back()->with('errorMessage', 'Data tidak ditemukan');
        }

        // =======================
        // ========== SELL =======
        // =======================
        if ($logjubelio->type == 'SELL') {

            $dataApi = json_decode($logjubelio->payload, true);

            if ($logjubelio->source != 1) {
                $dataApi = $this->getOrder($logjubelio->jubelio_order_id);

                if (isset($dataApi['error'])) {
                    return redirect()
                        ->route('jubelio.webhook.createManual', $logjubelio->id)
                        ->with('errorMessage', 'Gagal ambil data API: ' . $dataApi['message']);
                }
            }

            if (!$dataApi) return;

            $arrayStoreId     = $dataApi['store_id'];
            $arrayLocationId  = $dataApi['location_id'];
            $arrayItems       = $dataApi['items'];
            $arrayRunCount    = $logjubelio->logjubelio + 1;
            $arrayInvoice     = $dataApi['salesorder_no'];
            $arraySubTotal    = $dataApi['sub_total'];
            $arrayGrandTotal  = $dataApi['grand_total'];

            $jubelioSync = Jubeliosync::where('jubelio_store_id', $arrayStoreId)
                ->where('jubelio_location_id', $arrayLocationId)
                ->lockForUpdate()
                ->first();

            if (!$jubelioSync) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Data sync dengan aria tidak ditemukan');
            }

            $itemCodes = collect($arrayItems)
                ->pluck('item_code')
                ->map(fn($code) => strtoupper($code))
                ->unique();

            $existingProducts = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
                ->get(['id', 'code', 'name'])
                ->keyBy(fn($item) => strtoupper($item->code));

            $groupedData = collect($arrayItems)->partition(function ($item) use ($existingProducts) {
                return isset($existingProducts[strtoupper($item['item_code'])]);
            });

            $matched = $groupedData[0]->map(function ($item) use ($existingProducts) {
                $upperCode = strtoupper($item['item_code']);
                $product   = $existingProducts[$upperCode];

                return [
                    'itemId'   => $product->id,
                    'code'     => $product->code,
                    'name'     => $product->name,
                    'quantity' => $item['qty'],
                    'price'    => $item['price'],
                    'discount' => 0,
                    'subtotal' => $item['qty'] * $item['price'],
                ];
            })->values();

            $notMatched = $groupedData[1]->values();

            if ($notMatched->count() > 0) {
                $item_codes = array_column($notMatched->toArray(), 'item_code');
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'SKU tidak ditemukan: ' . implode(", ", $item_codes));
            }

            $cekTransaksi = Transaction::where('type', Transaction::TYPE_SELL)
                ->where('invoice', $arrayInvoice)
                ->first();

            if ($cekTransaksi) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Transaction sudah ada');
            }

            if (count($arrayItems) !== $matched->count()) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Jumlah item tidak sesuai');
            }

            $adjust = $arraySubTotal - $arrayGrandTotal;

            $dataJubelio = [
                "date"               => Carbon::now()->toDateString(),
                "due"                => null,
                "warehouse"          => $jubelioSync->warehouse_id,
                "customer"           => $jubelioSync->customer_id,
                "invoice"            => $arrayInvoice,
                "note"               => "generated by cron aria",
                "account"            => "7204",
                "amount"             => null,
                "paid"               => null,
                "addMoreInputFields" => $matched,
                "disc"               => "0",
                "adjustment"         => $this->toggleSign($adjust),
                "ongkir"             => "0",
            ];

            $createData = $this->createTransaction(Transaction::TYPE_SELL, (object) $dataJubelio);

            if ($createData['status'] == "200") {
                $logjubelio->update([
                    'run_count' => $arrayRunCount,
                    'error_type' => 10,
                    'error' => null,
                    'execute_by' => 0,
                    'status' => 2,
                ]);

                return redirect()->route('jubelio.webhook.order')
                    ->with('success', 'Transaction created');
            }

            return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                ->with('errorMessage', $createData['message']);
        }

        // =========================
        // ======== RETURN =========
        // =========================
        elseif ($logjubelio->type == 'RETURN') {

            $dataApi = json_decode($logjubelio->payload, true);
            $arrayRunCount = $logjubelio->logjubelio + 1;

            $cekTransaksiSell = Transaction::where('type', Transaction::TYPE_SELL)
                ->where('invoice', $dataApi['salesorder_no'])
                ->first();

            if (!$cekTransaksiSell) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Transaksi jual tidak ditemukan');
            }

            $itemCodes = collect($dataApi['items'])
                ->pluck('item_code')
                ->map(fn($c) => strtoupper($c))
                ->unique();

            $existingProducts = Item::whereIn(DB::raw('UPPER(code)'), $itemCodes)
                ->get(['id', 'code', 'name'])
                ->keyBy(fn($item) => strtoupper($item->code));

            $groupedData = collect($dataApi['items'])
                ->partition(fn($item) => isset($existingProducts[strtoupper($item['item_code'])]));

            $matched = $groupedData[0]->map(function ($item) use ($existingProducts) {
                $product = $existingProducts[strtoupper($item['item_code'])];

                return [
                    'itemId'   => $product->id,
                    'code'     => $product->code,
                    'name'     => $product->name,
                    'quantity' => $item['qty_in_base'],
                    'price'    => $item['price'],
                    'discount' => 0,
                    'subtotal' => $item['qty_in_base'] * $item['price'],
                ];
            })->values();

            $notMatched = $groupedData[1];

            if ($notMatched->count() > 0) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'SKU tidak ditemukan: ' . implode(", ", $notMatched->pluck('item_code')->toArray()));
            }

            $cekTransaksi = Transaction::where('type', Transaction::TYPE_RETURN)
                ->where('invoice', $dataApi['return_no'])
                ->first();

            if ($cekTransaksi) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Invoice retur sudah ada');
            }

            $jubelioSync = Jubeliosync::where('jubelio_store_id', $dataApi['store_id'])
                ->where('jubelio_location_id', $dataApi['location_id'])
                ->first();

            if (!$jubelioSync) {
                return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                    ->with('errorMessage', 'Data sync tidak ditemukan');
            }

            $adjust = $dataApi['sub_total'] - $dataApi['grand_total'];

            $dataJubelio = [
                "date"               => Carbon::now()->toDateString(),
                "warehouse"          => $jubelioSync->warehouse_id,
                "customer"           => $jubelioSync->customer_id,
                "invoice"            => $dataApi['return_no'],
                "description"        => $dataApi['salesorder_no'],
                "note"               => "generated by jubelio",
                "account"            => "7204",
                "addMoreInputFields" => $matched,
                "disc"               => "0",
                "adjustment"         => $this->toggleSign($adjust),
                "ongkir"             => "0"
            ];

            $createData = $this->createTransaction(Transaction::TYPE_RETURN, (object) $dataJubelio);

            if ($createData['status'] == "200") {
                $logjubelio->update([
                    'run_count'  => $arrayRunCount,
                    'error_type' => 10,
                    'status'     => 2,
                    'execute_by' => 0
                ]);

                return redirect()->route('jubelio.webhook.order')
                    ->with('success', 'Return berhasil dibuat');
            }

            return redirect()->route('jubelio.webhook.createManual', $logjubelio->id)
                ->with('errorMessage', $createData['message']);
        }
    }

    protected function toggleSign($value)
    {
        return -$value;
    }

    protected function createTransaction($type = null, $dataJubelio)
    {


        try {




            //start transaction
            DB::beginTransaction();

            $customer = Customer::find($dataJubelio->customer);
            $warehouse = Customer::find($dataJubelio->warehouse);

            // dd($customer,$warehouse);

            // $input = $dataJubelio;
            $transaction = new Transaction();
            $transaction->date = $dataJubelio->date;
            $transaction->type = $type;
            $transaction->adjustment     = $dataJubelio->adjustment;
            $transaction->user_id = Auth::id();
            $transaction->submit_type = 2;

            //    if($dataJubelio->note){
            //        $transaction->description = $dataJubelio->note;
            //    }else{

            //    }

            $transaction->description = $dataJubelio->description ?? '';
            $transaction->invoice = $dataJubelio->invoice;

            // if ($dataJubelio->due) {
            //     $transaction->due = $dataJubelio->due;
            // } else {
            //     $transaction->due = '0000-00-00';
            // }

            $transaction->due = '0000-00-00';

            $transaction->detail_ids = ' ';

            $transaction->save();
            switch ($type) {
                case Transaction::TYPE_BUY:
                case Transaction::TYPE_RETURN:
                    $transaction->sender_id = $customer->id;
                    $transaction->receiver_id = $warehouse->id;
                    break;
                case Transaction::TYPE_SELL:
                case Transaction::TYPE_RETURN_SUPPLIER:
                    $transaction->sender_id = $warehouse->id;
                    $transaction->receiver_id = $customer->id;
                    break;
                default: //don't update stats for move, production
                    break;
            }

            $transaction->init($type);

            // dd($dataJubelio->addMoreInputFields);
            //gets the transaction id
            if (!$transaction->save())


                throw new ModelException($transaction->getErrors(), __LINE__);

            if (!$details = $transaction->createDetails($dataJubelio->addMoreInputFields))
                throw new ModelException($transaction->getErrors(), __LINE__);


            //check ppn first
            $transaction->checkPPN($transaction->sender, $transaction->receiver);




            //add to customer stat
            // $sm = new StatManager;

            $sm = new StatManagerHelper();
            switch ($type) {
                case Transaction::TYPE_BUY:
                case Transaction::TYPE_RETURN:
                    //add balance to sender(supplier)
                    $sender_balance = $sm->add($transaction->sender_id, $transaction, true); //skip 1 because the transaction is already created?
                    if ($sender_balance === false)
                        throw new ModelException($sm->getErrors());

                    $transaction->sender_balance = $sender_balance;
                    break;
                case Transaction::TYPE_SELL:
                case Transaction::TYPE_RETURN_SUPPLIER:
                    $transaction->setAttribute('total', 0 - $transaction->total); //make negative

                    //deduct balance from receiver(customer)
                    $receiver_balance = $sm->deduct($transaction->receiver_id, $transaction, true);
                    if ($receiver_balance === false)
                        throw new ModelException($sm->getErrors());

                    $transaction->receiver_balance = $receiver_balance;

                    // $transaction->save();

                    // dd($receiver_balance,$transaction, $transaction->receiver_balance);
                    break;
                default: //don't update stats for move, production
                    break;
            }



            if (!$transaction->save())
                throw new $transaction->getErrors();





            InvoiceTrackerHelpers::flag($transaction);


            if ($type == Transaction::TYPE_SELL || $type == Transaction::TYPE_RETURN) {



                // Query
                $result = DB::table('transaction_details')
                    ->where('transaction_details.transaction_id', $transaction->id)
                    ->join('items', 'transaction_details.item_id', '=', 'items.id')
                    ->whereIn('transaction_details.transaction_type', [Transaction::TYPE_SELL, Transaction::TYPE_RETURN]) // Filter transaction_type 2 dan 15
                    ->selectRaw('
                    items.group_id,
                    MONTH(transaction_details.date) as bulan,
                    YEAR(transaction_details.date) as tahun,
                    transaction_details.sender_id,
                    transaction_details.transaction_type,
                    SUM(transaction_details.quantity) as sum_qty,
                    SUM(transaction_details.total) as sum_total
                ')
                    ->groupBy('items.group_id', DB::raw('MONTH(transaction_details.date)'), DB::raw('YEAR(transaction_details.date)'), 'transaction_details.sender_id', 'transaction_details.transaction_type')
                    ->orderBy('items.group_id') // Optional: Untuk urutan hasil
                    ->sharedLock()
                    ->get();

                $insertData = [];
                foreach ($result as $row) {
                    $insertData[] = [
                        'group_id' => $row->group_id,
                        'bulan' => $row->bulan,
                        'tahun' => $row->tahun,
                        'sender_id' => $row->sender_id,
                        'type' => $row->transaction_type,
                        'sum_qty' => (int)$row->sum_qty,
                        'sum_total' => (int)$row->sum_total,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // dd($insertData);

                $this->updateOrCreateStatsalesOptimized($insertData);
            }

            //commit db transaction
            DB::commit();

            // $dataJubelio->session()->flash('success', 'Transaction # ' . $transaction->id. ' created.');

            return $data = [
                'status' => '200',
                'message' => 'ok',
                'transaction_id' => $transaction->id,
            ];

            //    return redirect()->route('transaction.getDetail',$transaction->id)->with('success', 'Transaction # ' . $transaction->id. ' created.');

            // return response()->json([
            //     'url' => route('transaction.getDetail',$transaction->id,$transaction->date),
            // ]);


        } catch (ModelException $e) {

            DB::rollBack();

            if ($e->getCode() == 1213) {

                return $data = [
                    'status' => '500',
                    'message' => $e->getMessage(),
                ];
            }

            return $data = [
                'status' => '422',
                'message' => $e->getErrors()['error'][0],
            ];


            // return response()->json($e->getErrors(), 500);

        } catch (\Exception $e) {
            DB::rollBack();

            return $data = [
                'status' => '422',
                'message' => $e->getMessage(),
            ];

            //    return redirect()->back()->withInput()->with('errorMessage',$e->getMessage());

            // return response()->json($e->getMessage(), 500);

        }
    }

    public function updateOrCreateStatsalesOptimized(array $data)
    {
        foreach ($data as $entry) {
            $existing = DB::table('stat_sells')
                ->where('group_id', $entry['group_id'])
                ->where('bulan', $entry['bulan'])
                ->where('tahun', $entry['tahun'])
                ->where('sender_id', $entry['sender_id'])
                ->sharedLock()
                ->first();

            if ($existing) {
                // Jika data ditemukan, update sum_qty dan sum_total
                DB::table('stat_sells')
                    ->where('id', $existing->id)
                    ->incrementEach([
                        'sum_qty' => $entry['sum_qty'],
                        'sum_total' => $entry['sum_total']
                    ]);
            } else {
                // Jika tidak ditemukan, insert data baru
                DB::table('stat_sells')->insert([
                    'group_id' => $entry['group_id'],
                    'bulan' => $entry['bulan'],
                    'tahun' => $entry['tahun'],
                    'sender_id' => $entry['sender_id'],
                    'type' => $entry['type'],
                    'sum_qty' => $entry['sum_qty'],
                    'sum_total' => $entry['sum_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['message' => 'Data processed successfully'], 200);
    }

    public function createSolved($id)
    {

        $logjubelio = Jubelioorder::findOrFail($id);

        if ($logjubelio->source == 1) {

            $data = json_decode($logjubelio->payload, true);
        } else {
            $data = $this->getOrder($logjubelio->jubelio_order_id);
        }

        $adjust = $data['sub_total'] - $data['grand_total'];

        $jubelioSync = Jubeliosync::where('jubelio_store_id', $data['store_id'])->where('jubelio_location_id', $data['location_id'])->first();

        $sid = $id;

        return view('jubelio.webhook.solved', compact('jubelioSync', 'data', 'adjust', 'sid'));
    }

    public function storeSolved($id)
    {

        $logjubelio = Jubelioorder::findOrFail($id);
        $logjubelio->error_type = 10;
        $logjubelio->error = null;
        $logjubelio->execute_by = Auth::id();
        $logjubelio->status = 2;

        $logjubelio->save();

        return redirect()->route('jubelio.webhook.order')->with('success', 'Jubelio order Solved');
    }
}
