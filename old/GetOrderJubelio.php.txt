<?php

namespace App\Console\Commands;

use App\Helpers\CronHelper;
use App\Models\Crongetorder;
use App\Models\Crongetorderdetail;
use App\Models\Cronrun;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetOrderJubelio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jubelio:get-orders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('jubelio:get-orders run at: ' . now());

        try {
            $data = Crongetorder::with('orderDetail')->withCount('orderDetail')->orderBy('created_at', 'desc')->first();

            if (!$data) {
                throw new \Exception('Data Crongetorder tidak ditemukan.');
            }

            if ($data->status == 1) {
                throw new \Exception('Data Crongetorder tidak aktif.');
            }

            $dateFrom = Carbon::parse( $data->from, 'UTC')->startOfDay()->format('Y-m-d\TH:i:s\Z');
            $dateTo = Carbon::parse( $data->from, 'UTC')->addDays($data->to)->endOfDay()->format('Y-m-d\TH:i:s\Z');


            $token = Cache::get('jubelio_data')['token'] ?? null;

            if (!$token) {
                throw new \Exception('Token Jubelio tidak ditemukan di cache.');
            }

            if (
                ($data->count == 0 && $data->total == 0 && $data->status == 0) ||
                ($data->count != $data->total && $data->status == 0)
            ) {
               
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'authorization' => $token,
                ])->get('https://api2.jubelio.com/sales/orders/', [
                    'page' => $data->count+1,
                    'pageSize' => 200,
                    'transactionDateFrom' => $dateFrom,
                    'transactionDateTo' => $dateTo
                ]);

                if ($response->failed()) {
                    Log::error('API Jubelio gagal merespon', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);

                    throw new \Exception('Gagal mendapatkan data dari API Jubelio. Status: ' . $response->status());
                }

                $responData =  $response->json(); // atau json_decode($response->body(), true);

                // Log::info('jubelio:get-orders data: ' . $responData['totalCount']);
        

                if($responData['totalCount'] == 0){

                    $data->step = 3;
                    $data->status = 1;

                    $data->save(); 

                    $cronStatus = Cronrun::where('name', 'get_order')->first();

                    $cronStatus->status = 0;

                    $cronStatus->save();

                    CronHelper::refreshCronCache();

                }else{
                    
                    if($data->total < 1){

                        $a = $responData['totalCount'];
                        $b = 200;

                        $hasil = (int)ceil($a / $b);

                        $data->total = $hasil;

                        $data->save();
                
                    }

                    $dataArray = []; 

                

                    if(count($responData['data']) > 0){
                        
                        foreach ($responData['data'] as $row) {
                            $dataArray[] = [
                                'get_order_id' => $data->id,
                                'order_id' =>  $row['salesorder_id'],
                                'invoice' => $row['salesorder_no'],
                                'location_id' => $row['location_name'],
                                'store_id' => $row['store_name'],
                                'status' => $row['internal_status'],
                                'is_canceled' => $row['is_canceled'],
                                'payload' => json_encode($row),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        DB::table('crongetorderdetails')->insert($dataArray);

                        $data->increment('count');
            
                    }
                }

            } elseif ($data->count == $data->total && $data->total != 0 && $data->status == 0) {
                if($data->step == 1){

                    Crongetorderdetail::where('get_order_id', $data->id)
                        ->whereNotIn('status', ['SHIPPED', 'COMPLETED', 'RETURNED']) 
                        ->delete();

                    Crongetorderdetail::where('get_order_id', $data->id)
                        ->where('is_canceled', 'Y')
                        ->delete();
                        
                        $data->step = 2;
                        
                        $data->save(); 
                        


                    }else if($data->step == 2){

                        
                        $ids = Crongetorderdetail::where(function ($query) {
                            $query->whereHas('transaksi')
                                ->orWhereHas('jubelio');
                        })
                        ->limit(500)
                        ->pluck('id');

                        if($ids->count() > 0){

                            Crongetorderdetail::whereIn('id', $ids)->delete();

                        }else{

                            
                            $data->step = 3;
                            $data->status = 1;

                            $data->save(); 

                            $cronStatus = Cronrun::where('name', 'get_order')->first();

                            $cronStatus->status = 0;

                            $cronStatus->save();

                            CronHelper::refreshCronCache();
                        }


                    }

            } elseif ($data->count == $data->total && $data->total != 0 && $data->status == 1) {
                // Proses Selesai
            }

          
            // Lanjutkan proses dengan $data...

        } catch (\Exception $e) {
            

            Log::error('Terjadi kesalahan saat mengambil data dari Jubelio API', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Jika ingin kirim respon ke front-end
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }


    }
}
