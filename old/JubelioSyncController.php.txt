<?php

namespace App\Http\Controllers;

use App\Helpers\JubelioHelper;
use App\Models\Customer;
use App\Models\Jubeliosync;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class JubelioSyncController extends Controller
{

    public function index(){
        $dataList = Jubeliosync::with(['warehouse','customer']);
        if(Request('name')) {
			$name = str_replace(' ', '%', Request('name'));
			$dataList = $dataList->where('jubelio_location_name','LIKE',"%$name%");
		}
		if($id = Request('id')) {
			$dataList = $dataList->where('memberId','=', $id);
		}
        
        $dataList = $dataList->orderBy('created_at','desc')->paginate(50)->withQueryString();

        return view('jubelio.sync.index',compact('dataList'));
    }

    public function create()
    {

        // $dataApi = JubelioHelper::checkOrUpdateData('jub', 'new_value');

        

        $response = Http::withHeaders([
            'Authorization' => Cache::get('jubelio_data')['token']
        ])->get('https://api2.jubelio.com/locations/', [
            'page' => 1,
            'pageSize' => 200
        ]);

        $dataList = $response->json();

        // dd($dataList);
        

        $dataListPropWarehouse = [
			"label" => "Warehouse",
			"id" => "warehouse",
			"idList" => "datalistWh",
			"idOption" => "datalistOptionsWh",
			"type" => Customer::TYPE_WAREHOUSE,
			
		];

        $dataListPropCustomer = [
			"label" => "Customer",
			"id" => "customer",
			"idList" => "datalistCs",
			"idOption" => "datalistOptionsCs",
			"type" => Customer::TYPE_CUSTOMER,
			
		];
       
 

        return view('jubelio.sync.create',compact('dataList','dataListPropWarehouse','dataListPropCustomer'));
    }

    public function store(Request $request){

        $rules = [
            'location_id'  => 'required',
            'warehouse'  => 'required',
           
            
		];

        $attributes = [
            'location_id'  => 'Jubelio location',
            'warehouse'  => 'Warehouse',
            'customer' => 'Customer',
            
        ];

        $this->validate($request, $rules, [], $attributes);


        // $dataApi = JubelioHelper::checkOrUpdateData('jub', 'new_value');

        $response = Http::withHeaders([
            'Authorization' => Cache::get('jubelio_data')['token']
        ])->get('https://api2.jubelio.com/locations/'.$request->location_id);

        $dataList = $response->json();

        // dd($dataList);

        if($dataList['warehouse']){
            $dataRow = $dataList['warehouse'];
        }else{
            $dataRow = $dataList['stores'];
        }

        $syncArray = [];

        foreach ($dataRow as $data) {
            $syncArray[] = [
                'jubelio_store_id' => $data['store_id'],
                'jubelio_store_name' => $data['store_name'],
                'jubelio_location_id' => $request->location_id,
                'jubelio_location_name' => $request->locationName,
                'warehouse_id' => $request->warehouse,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        DB::table('jubeliosyncs')->insert($syncArray);

        // dd($syncArray);

     
        // $data = new Jubeliosync();

        // $data->jubelio_location_id = $request->location_id;
        // $data->jubelio_location_name = $request->locationName;
        // $data->warehouse_id = $request->warehouse;
        // $data->customer_id = $request->customer;

        // $data->save();

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync created.');
      

    }

    public function edit($id)
    {

        $data = Jubeliosync::find($id);

        $dataListPropWarehouse = [
			"label" => "Warehouse",
			"id" => "warehouse",
			"idList" => "datalistWh",
			"idOption" => "datalistOptionsWh",
			"type" => Customer::TYPE_WAREHOUSE,
        
		];

        if($data->warehouse_id){
            $dataListPropWarehouse["default"] = $data->warehouse_id;
        }


        $dataListPropCustomer = [
			"label" => "Customer",
			"id" => "customer",
			"idList" => "datalistCs",
			"idOption" => "datalistOptionsCs",
			"type" => Customer::TYPE_CUSTOMER,
          
			
		];
       

        if($data->customer_id){
            $dataListPropCustomer["default"] = $data->customer_id;
        }

       

       
 

        return view('jubelio.sync.edit',compact('data','dataListPropCustomer','dataListPropWarehouse'));
    }

    public function update(Request $request,$id){

        $rules = [
            'customer'  => 'required',
            'warehouse'  => 'required',
            
		];

        $attributes = [
            'location_id'  => 'Jubelio location',
            'warehouse'  => 'Warehouse',
            'customer' => 'Customer',
            
        ];

        $this->validate($request, $rules, [], $attributes);

        $data = Jubeliosync::where('id',$id)->update(['warehouse_id'=>$request->warehouse,'customer_id'=>$request->customer]);
        
        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync created.');
      

    }

    public function delete($id){
        $data = Jubeliosync::find($id);

        $data->delete();

        return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio sync deleted.');
    }

    public function getBin($id){
        $data = Jubeliosync::find($id);

        $response = Http::withHeaders([ 
            'Content-Type'=> 'application/json', 
            'authorization'=> Cache::get('jubelio_data')['token'], 
        ]) 
        ->get('https://api2.jubelio.com/wms/default-bin/'.  $data->jubelio_location_id); 

    

        
        if ($response->successful()) {
            $result = json_decode($response->body(), true); // atau json_decode($response->body(), true)

            $data->bin_id = $result['bin_id'];
            $data->save();

            return redirect()->route('jubelio.sync.index')->with('success', 'Jubelio updated bin id.');
          
        } else {
            // Ambil pesan error dari response
            $error =json_decode($response->body(), true); // atau json_decode($response->body(), true)
            $message = $error['message'] ?? 'Terjadi kesalahan.';
            
            return redirect()->route('jubelio.sync.index')->with('fail',  $message);
        }


        

       
    }
}
