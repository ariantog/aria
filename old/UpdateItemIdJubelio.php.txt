<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Jubeliosync;
use App\Models\WarehouseItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateItemIdJubelio extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jubelio:item-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Edit item id from jubelio';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Log::info('jubelio:item-update dijalankan pada: ' . now());
        
        // $item = Item::whereNull('jubelio_item_id')->orderBy('id','desc')->first();

        $jubelioSync = Jubeliosync::select('warehouse_id')->groupBy('warehouse_id')->pluck('warehouse_id')->toArray();

        $whItem = WarehouseItem::with(['item' => function ($q) {
            $q->orderBy('id');
        }])
        ->whereHas('item', function ($q) {
            $q->whereNull('jubelio_item_id');
        })
        ->whereIn('warehouse_id', $jubelioSync)
        ->orderBy('id', 'asc')
        ->first();


        if($whItem){

            $itemCode = $whItem->item->code;

            $response = Http::withHeaders([ 
                'Content-Type'=> 'application/json', 
                'authorization'=> Cache::get('jubelio_data')['token'], 
            ]) 
            ->get('https://api2.jubelio.com/inventory/items/to-stock/',[
                'q' => $itemCode,
            ]); 
    
            $data = json_decode($response->body(), true);

            if($data['totalCount'] == 0){
                DB::table('items')->where('code',$itemCode)->update([
                    'jubelio_item_id' => 0, // Kolom yang diperbarui
                ]);
               
                DB::table('cron_stat_runs')->incrementEach([
                    'runner' => 1,
                    'failed' => 1,
                ]);
            }else{

                $filtered = collect($data['data'])->firstWhere('item_code', $itemCode);
                
                DB::table('items')->where('code',$itemCode)->update([
                    'jubelio_item_id' =>  $filtered['item_id'], // Kolom yang diperbarui
                ]);

                DB::table('cron_stat_runs')->increment('runner');
               
            }
        }
    }
}
