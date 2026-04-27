public function render(): View|Closure|string
    {
        $dataList = StatSell::with('sender','group')->where('sender_id',$this->cid)->orderBy('bulan','desc')->orderBy('tahun','desc');

        if(Request('bulan')){
			$dataList = $dataList->where('bulan',Request('bulan'));
		}

		if(Request('tahun')){
			$dataList = $dataList->where('tahun',Request('tahun'));
		}

        if(Request('group')){
			$dataList = $dataList->where('group',Request('group'));
		}

		if(Request('type')){
			$dataList = $dataList->where('type',Request('type'));
		}

		$dataList = $dataList->paginate(1000)->withQueryString();

        return view('components.customer.itemsale',compact('dataList'));
    }