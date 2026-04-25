public function index(Request $request){
        $dataList = StatSell::with('group')->orderBy('bulan','desc')->orderBy('tahun','desc');

        if($request->bulan){
			$dataList = $dataList->where('bulan',$request->bulan);
		}

		if($request->tahun){
			$dataList = $dataList->where('tahun',$request->tahun);
		}

        if($request->group){
			$dataList = $dataList->where('group',$request->group);
		}

		if($request->type){
			$dataList = $dataList->where('type',$request->type);
		}

		$dataList = $dataList->paginate(100)->withQueryString();

        // dd($dataList->toArray());

        return view('report.itemsale',compact('dataList'));
    }