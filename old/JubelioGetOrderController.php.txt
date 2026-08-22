<?php

namespace App\Http\Controllers;

use App\Helpers\CronHelper;
use App\Models\Crongetorder;
use App\Models\Crongetorderdetail;
use App\Models\Cronrun;
use App\Models\Logjubelio;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JubelioGetOrderController extends Controller
{
    public function index(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();

        // Total tahapan

        // dd($crons = CronHelper::getCachedCrons()->where('status', 1));

        $dataList = [];

        $persentase = 0;

        $dateFrom ='';
        $dateTo ='';


        if($data){

            $dateFrom = Carbon::parse( $data ->from, 'UTC')->startOfDay()->format('Y-m-d\TH:i:s\Z');
            $dateTo = Carbon::parse( $data ->from, 'UTC')->addDays($data ->to)->endOfDay()->format('Y-m-d\TH:i:s\Z');

            $dataList = Crongetorderdetail::with('transaksi','jubelio')->where('get_order_id',$data->id)->paginate('200');


            if($data->total > 0 && $data->count > 0 ){

                $totalTahapan = $data->total;

                // Tahapan yang telah selesai (bisa diganti sesuai progres)
                $tahapanSelesai = $data->count; // contoh: sudah menyelesaikan 3 dari 5 tahapan

                // Hitung persentase
                $persentase = ($tahapanSelesai / $totalTahapan) * 100;

                // Bulatkan ke 2 angka di belakang koma (opsional)
                $persentase = (int) round($persentase);

            }

        }
        
        return view('jubelio.getOrder.index', compact('data','persentase','dataList','dateFrom','dateTo'));
    }

    public function store(Request $request) {

        $rules = [          
            'from' => ['required'],
            'to' => ['required'],
		];

        $messages = [
            'required' => ':attribute harus diisi',
            
        ];

        $attributes = [

            
        ];

        $this->validate($request, $rules, $messages, $attributes);

       

        $data = new Crongetorder();

        $data->from = $request->from;
        $data->to =$request->to;

        $data->save();

        $cron = Cronrun::where('name', 'get_order')->first();

        $cron->status = 1;

        $cron->save();

        CronHelper::refreshCronCache();


        return redirect()->route('jubelio.order.getall')->with('success','Get order berhasil disimpan');

       
        
    }

    public function cekTransaction(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();

        $trans = Transaction::whereDate('date', '>=', $data->from)
                ->whereDate('date', '<=', $data->to)
                ->where('submit_type',2)
                ->pluck('invoice')
                ->toArray();

      

        $detail = Crongetorderdetail::whereIn('invoice', $trans)->delete();

        $data->cek_transaction = 1;
        $data->save();

        return redirect()->route('jubelio.order.getall')->with('success','Menghapus data');
    }

     public function cekLog(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();
        Crongetorderdetail::where('get_order_id', $data->id)
            ->where(function ($query) {
                $query->whereHas('transaksi')
                    ->orWhereHas('logJubelio');
            })
            ->delete();
            
        // $data->cek_log = 1;
        // $data->save();

        return redirect()->route('jubelio.order.getall')->with('success','Menghapus data');
    }

    public function deleteAll(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();

        $detail = Crongetorderdetail::where('get_order_id',$data->id)->delete();

        $data->delete();

        return redirect()->route('jubelio.order.getall')->with('success','Menghapus data');
    }

    public function toLog(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();

        $detail = Crongetorderdetail::where('get_order_id',$data->id)->get();

        $dataArray = []; 

            if($detail ){

                foreach ($detail as $row) {
                    $dataArray[] = [
                        'jubelio_order_id'  => $row->order_id,
                        'source'            => 2,
                        'invoice'           => $row->invoice,
                        'type'              => 'SELL',
                        'order_status'      => $row->status,
                        'run_count'         => 0,
                        'error_type'        => null,
                        'error'             => null,
                        'payload'           => $row->payload,
                        'execute_by'        => null,
                        'status'            => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

            }

        DB::table('jubelioorders')->insert($dataArray);

        Crongetorderdetail::where('get_order_id',$data->id)->delete();

        $data->delete();

        return redirect()->route('jubelio.order.getall')->with('success','Order dipindah ke logjubelio');
    }

    public function reset(){

        $data = Crongetorder::withCount('orderDetail')->orderBy('created_at','desc')->first();

        $detail = Crongetorderdetail::where('get_order_id',$data->id)->delete();

        $data->delete();

        $cronStatus = Cronrun::where('name', 'get_order')->first();

        $cronStatus->status = 0;

        $cronStatus->save();

        CronHelper::refreshCronCache();

        return redirect()->route('jubelio.order.getall')->with('success','Reseted');
    }
}
