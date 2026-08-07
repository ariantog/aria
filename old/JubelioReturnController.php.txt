<?php

namespace App\Http\Controllers;

use App\Exceptions\ModelException;
use App\Helpers\StatManagerHelper;
use App\Models\Customer;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioReturnController extends Controller
{
    public function index(Request $request){

        
        $dataList = Jubelioreturn::with('user')->orderBy('updated_at','asc');

        if($request->status == "SOLVED"){
            $dataList = $dataList->whereIn('status',[1,2]);
        }else{
            $dataList = $dataList->where('status',0);
        }
       
        
        if($request->from && $request->to){
			$dataList = $dataList->whereDate('updated_at','>=',$request->from)->whereDate('updated_at','<=',$request->to);
		}

		if($request->invoice){
			$dataList = $dataList->where('invoice',$request->invoice);
		}
        
        $dataList = $dataList->paginate(50)->withQueryString();

        // dd($dataList);

        return view('jubelio.return.index',compact('dataList'));
    }

    
	public function jubelioReturn($id){

        $returnData = Jubelioreturn::find($id);

		$data = Transaction::with(['receiver','sender','user','transactionDetail','transactionDetail.item','transactionDetail.item.group'])->where('id',$returnData->transaction_id	)->first();

        $rid = $id;

		return view('jubelio.return.detail',compact('data','rid'));
	}

    public function jubelioReturnPost($id, Request $request){

        $returnData = Jubelioreturn::find($id);

        if(!$request->return_item){

            return redirect()->back()->with('fail','Item belum dipilih');
        }

        // dd('stop');

		$transactionData = Transaction::with(['receiver','sender','user','transactionDetail','transactionDetail.item','transactionDetail.item.group'])->where('id',$returnData->transaction_id	)->first();

		
		$item = TransactionDetail::with('item')->where('transaction_id',$transactionData->id)->whereIn('item_id', $request->return_item)->get();

		$moreItem = [];
		

		foreach ($item as $data) {
			$moreItem[] = [
				'itemId'   => $data->item_id,
				'code'     => $data->item->code,
				'name'     => $data->item->name,
				'quantity' => $data->quantity,
				'price'    => $data->price,
				'discount' => 0,
				'subtotal' => $data->quantity*$data->price,
			];
		}

		$dataJubelio = [
			"date" => Carbon::now()->toDateString(),
			"due" => null,
			"warehouse" => $transactionData->sender_id,
			"customer" => $transactionData->receiver_id,
			"invoice" => $transactionData->invoice,
			"note" => " ",
			"account" => "7204",
			"amount" => null,
			"paid" => null,
			"addMoreInputFields" => $moreItem,
			"disc" => "0",
			"adjustment" =>  $request->adjustment,
			"ongkir" => "0"
		];

		$dataCollect =  (object) $dataJubelio;

		

		$transactionData->jubelio_return = 2;

		$transactionData->save();
		
		$this->createTransaction(Transaction::TYPE_RETURN, $dataCollect);

        $returnData->status = 1;
        $returnData->confirmed_by = Auth::user()->id;

        $returnData->save();

		return redirect()->route('transaction.index')->with('success', 'Return created.');

	}

    public function createSolved($id){

        $logjubelio = Jubelioreturn::findOrFail($id);

        $response = Http::withHeaders([ 
            'Content-Type'=> 'application/json', 
            'authorization'=> Cache::get('jubelio_data')['token'], 
        ]) 
        ->get('https://api2.jubelio.com/sales/orders/'.$logjubelio->order_id); 

        $data = json_decode($response->body(), true);

        $adjust = $data['sub_total'] - $data['grand_total'];

        $jubelioSync = Jubeliosync::where('jubelio_store_id', $data['store_id'])->where('jubelio_location_id',$data['location_id'])->first();
        
        $sid = $id;

        return view('jubelio.return.solved',compact('jubelioSync','data','adjust','sid'));
    }

    public function storeSolved($id){
        $logjubelio = Jubelioreturn::findOrFail($id);
        $logjubelio ->status = 1;
        $logjubelio->confirmed_by = Auth::user()->id;
    
        $logjubelio->save();

        return redirect()->route('transaction.index')->with('success', 'Return finished.');
    }

	protected function createTransaction($type = null, $dataJubelio)
    {
    
        $maxRetries = 5; // Jumlah maksimal percobaan
        $attempts = 0;

        while ($attempts < $maxRetries) {
            try {

                $class = array();

                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                
                //start transaction
                DB::beginTransaction();

                $customer = Customer::find($dataJubelio->customer);
                $warehouse = Customer::find($dataJubelio->warehouse);

                // dd($customer,$warehouse);

                // $input = $dataJubelio;
                $transaction = new Transaction();
                $transaction->date = $dataJubelio->date;
                $transaction->type = $type;
                $transaction->adjustment	 = $dataJubelio->adjustment;
                $transaction->user_id =-100;
                $transaction->submit_type = 2;



                $transaction->description = " ";
                $transaction->invoice = $dataJubelio->invoice;

                if($dataJubelio->due){
                    $transaction->due = $dataJubelio->due;
                }else{
                    $transaction->due = '0000-00-00';
                }

                $transaction->detail_ids = ' ';
                
                $transaction->save();
                switch($type)
                {
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
                if(!$transaction->save())

                    
                    throw new ModelException($transaction->getErrors(), __LINE__);

                if(!$details = $transaction->createDetails($dataJubelio->addMoreInputFields))
                    throw new ModelException($transaction->getErrors(), __LINE__);
                

                //check ppn first
                $transaction->checkPPN($transaction->sender, $transaction->receiver);

            


                //add to customer stat
                // $sm = new StatManager;

                $sm = new StatManagerHelper();
                switch($type)
                {
                    case Transaction::TYPE_BUY:
                    case Transaction::TYPE_RETURN:
                        //add balance to sender(supplier)
                        $sender_balance = $sm->add($transaction->sender_id,$transaction,true); //skip 1 because the transaction is already created?
                        if($sender_balance === false)
                            throw new ModelException($sm->getErrors());

                        $transaction->sender_balance = $sender_balance;
                        break;
                    case Transaction::TYPE_SELL:
                    case Transaction::TYPE_RETURN_SUPPLIER:
                        $transaction->setAttribute('total',0 - $transaction->total); //make negative

                        //deduct balance from receiver(customer)
                        $receiver_balance = $sm->deduct($transaction->receiver_id,$transaction,true);
                        if($receiver_balance === false)
                            throw new ModelException($sm->getErrors());

                        $transaction->receiver_balance = $receiver_balance;

                        // $transaction->save();

                        // dd($receiver_balance,$transaction, $transaction->receiver_balance);
                        break;
                    default: //don't update stats for move, production
                        break;
                }

                

                if(!$transaction->save())
                    throw new $transaction->getErrors();

                if($type == Transaction::TYPE_SELL || $type == Transaction::TYPE_RETURN){

                

                    // Query
                    $result = DB::table('transaction_details')
                    ->where('transaction_details.transaction_id',$transaction->id)
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
                        'deadLock' => 0
                    ];

                //    return redirect()->route('transaction.getDetail',$transaction->id)->with('success', 'Transaction # ' . $transaction->id. ' created.');
                
                // return response()->json([
                //     'url' => route('transaction.getDetail',$transaction->id,$transaction->date),
                // ]);

                break;
            } catch(ModelException $e) {
                
                DB::rollBack();

                if ($e->getCode() == 1213) {
                    $attempts++;
                    Log::warning("Deadlock terdeteksi, mencoba ulang ($attempts/$maxRetries)...");
    
                    // Tunggu sebentar sebelum retry (misalnya 100ms)
                    usleep(200000);
                } else {
                    return $data = [
                        'status' => '422',
                        'message' => $e->getMessage(),
                        'deadLock' => 0
                    ];

                  
                }

                return $data = [
                    'status' => '422',
                    'message' => $e->getErrors()['error'][0],
                    'deadLock' => 0
                ];

                
                // return response()->json($e->getErrors(), 500);
            
            } catch(\Exception $e) {
                DB::rollBack();

                return $data = [
                        'status' => '422',
                        'message' => $e->getMessage(),
                        'deadLock' => 0
                    ];

                //    return redirect()->back()->withInput()->with('errorMessage',$e->getMessage());

                // return response()->json($e->getMessage(), 500);
                
            }
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

}
