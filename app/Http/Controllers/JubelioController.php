<?php

namespace App\Http\Controllers;

use App\Actions\Jubelio\AdjustStock;
use App\Enums\TransactionType;
use App\Models\Jubelio;
use App\Models\Jubelioorder;
use App\Models\Jubelioreturn;
use App\Models\Jubeliosync;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class JubelioController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);
        $q = Jubelioorder::query()->with('user')->orderBy('updated_at', 'desc');
        if ($request->status == 'warning') $q->where('status', 2)->where('error_type', 2);
        elseif ($request->status == 'success') $q->where('status', 2)->where('error_type', 10);
        elseif ($request->status == 'error') $q->where('status', 1)->where('error_type', 1);
        elseif ($request->status == 'pending') $q->where('status', 0);
        $q->when($request->invoice, fn ($q) => $q->where('invoice', 'like', '%'.$request->invoice.'%'));
        $stats = Jubelioorder::selectRaw("COUNT(CASE WHEN status=0 THEN 1 END) as pending, COUNT(CASE WHEN status=2 AND error_type=10 THEN 1 END) as success, COUNT(CASE WHEN status=2 AND error_type=2 THEN 1 END) as warning, COUNT(CASE WHEN status=1 AND error_type=1 THEN 1 END) as error")->first();
        return view('jubelio.index', ['orders' => $q->paginate(15)->withQueryString(), 'stats' => ['pending'=>(int)$stats->pending,'success'=>(int)$stats->success,'warning'=>(int)$stats->warning,'error'=>(int)$stats->error], 'filters' => $request->only(['status','invoice']), 'flash' => ['success' => session('success'), 'error' => session('error') ?? session('errorMessage')]]);
    }

    public function show(Jubelioorder $jubelio): View
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        $order = $jubelio->load(['user', 'trx']);

        return view('jubelio.show', [
            'order' => $order,
            'summary' => $order->payloadSummary(),
            'items' => $order->payloadItems(),
            'transactionsUrl' => $order->transactionsSearchUrl(),
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function payload(Jubelioorder $jubelio): JsonResponse
    {
        Gate::authorize(Jubelio::getPermissions()['view']);

        return response()->json([
            'payload' => $jubelio->payloadArray(),
        ]);
    }

    public function webhookOrder(Request $request): JsonResponse
    {
        $s = config('services.jubelio.webhook_secret');
        if ($request->header('Sign') !== hash_hmac('sha256', trim($request->getContent()).$s, $s, false)) return response()->json(['error'=>'Invalid signature'], 403);
        $d = $request->all();
        if (($d['status']??'') === 'SHIPPED') { if (Carbon::parse($d['transaction_date'])->lt(Carbon::parse('2025-03-06'))) return response()->json(['status'=>'ok','message'=>'Before threshold.']); if (Jubelioorder::where('invoice',$d['salesorder_no'])->where('type','SELL')->where('order_status',$d['status'])->exists()) return response()->json(['status'=>'ok','message'=>'Already exists']); Jubelioorder::create(['jubelio_order_id'=>$d['salesorder_id'],'source'=>1,'invoice'=>$d['salesorder_no'],'type'=>'SELL','order_status'=>$d['status'],'run_count'=>0,'payload'=>json_encode($d),'status'=>0]); return response()->json(['status'=>'ok','message'=>'Saved']); }
        if (($d['status']??'') === 'CANCELED') { $t = Transaction::where('type',TransactionType::Sell->value)->where('invoice_number',$d['salesorder_no'])->first(); if (! $t) return response()->json(['status'=>'ok','message'=>'Not found']); if (Jubelioreturn::where('order_id',$d['salesorder_id'])->exists()) return response()->json(['status'=>'ok','message'=>'Return exists']); Jubelioreturn::create(['order_id'=>$d['salesorder_id'],'transaction_id'=>$t->id,'method_pay'=>$d['payment_method']??null,'invoice'=>$d['salesorder_no'],'pesan'=>$d['cancel_reason_detail']??null,'location_name'=>$d['location_name']??null,'store_name'=>$d['source_name']??null,'status'=>0,'confirmed_by'=>0]); return response()->json(['status'=>'ok','message'=>'Cancel saved']); }
        return response()->json(['status'=>'ok','message'=>'Status '.($d['status']??'unknown')]);
    }

    public function transactionSync(Request $request): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);
        $types = [TransactionType::Sell->value=>'SELL',TransactionType::ReturnSupplier->value=>'RETURN SUPPLIER',TransactionType::Buy->value=>'BUY',TransactionType::Return->value=>'RETURN',TransactionType::Move->value=>'MOVE'];
        $q = Transaction::with(['sender','receiver'])->where('submit_type',Transaction::SUBMIT_TYPE_MANUAL)->when($request->display,fn($q)=>$q->where('sync_hide',$request->display),fn($q)=>$q->where('sync_hide','N'))->when($request->date,fn($q)=>$q->whereDate('date','=',$request->date))->when($request->invoice,fn($q)=>$q->where('invoice_number','like',"%$request->invoice%"))->when($request->type,fn($q)=>$q->where('type',$request->type));
        if (! $request->invoice) $q->where(fn($q)=>$q->where(fn($q)=>$q->whereIn('type',[TransactionType::Sell->value,TransactionType::ReturnSupplier->value])->whereNull('a_submit_by')->whereIn('sender_id',fn($s)=>$s->select('warehouse_id')->from('jubeliosyncs')))->orWhere(fn($q)=>$q->whereIn('type',[TransactionType::Buy->value,TransactionType::Return->value])->whereNull('b_submit_by')->whereIn('receiver_id',fn($s)=>$s->select('warehouse_id')->from('jubeliosyncs')))->orWhere(fn($q)=>$q->where('type',TransactionType::Move->value)->where(fn($q)=>$q->where(fn($w)=>$w->whereIn('sender_id',fn($s)=>$s->select('warehouse_id')->from('jubeliosyncs'))->whereNull('a_submit_by'))->orWhere(fn($w)=>$w->whereIn('receiver_id',fn($s)=>$s->select('warehouse_id')->from('jubeliosyncs'))->whereNull('b_submit_by')))));
        $t = $q->orderBy('date','desc')->orderBy('id','desc')->paginate(200)->withQueryString();
        $sw = Jubeliosync::pluck('warehouse_id')->toArray();
        $t->getCollection()->transform(function($i)use($sw){$i->sync_cek=$this->syncCek($i,$sw);$i->type_name=$i->getTypeLabel();$i->description=$i->description??$i->notes??'';return $i;});
        return view('jubelio.transaction-sync',['transactions'=>$t,'types'=>$types,'filters'=>$request->only(['date','invoice','type','display']),'flash'=>['success'=>session('success'),'error'=>session('error')]]);
    }

    public function detailJubelioSync(Transaction $t): View
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);
        $t->load(['receiver','sender','user','submitByA','submitByB','details.item.group'])->loadCount(['details as item_with_jubelio_count'=>fn($q)=>$q->whereHas('item',fn($q)=>$q->where(fn($q)=>$q->whereNull('jubelio_item_id')->orWhere('jubelio_item_id','<',1)))]);
        $syncA = in_array($t->type,[TransactionType::Sell->value,TransactionType::ReturnSupplier->value,TransactionType::Move->value]);
        $syncB = in_array($t->type,[TransactionType::Buy->value,TransactionType::Return->value,TransactionType::Move->value]);
        $adA=0;$adB=0;$jA=null;$jB=null;
        if($syncA){$jsA=Jubeliosync::where('warehouse_id',$t->sender_id)->first();if($jsA){$adA=2;$jA=$jsA->jubelio_location_name;}}
        if($syncB){$jsB=Jubeliosync::where('warehouse_id',$t->receiver_id)->first();if($jsB){$adB=1;$jB=$jsB->jubelio_location_name;}}
        return view('jubelio.detail-sync',['data'=>$t,'can_sync'=>$t->submit_type===Transaction::SUBMIT_TYPE_MANUAL,'JubelioA'=>$jA,'JubelioB'=>$jB,'adJustTypeA'=>$adA,'adJustTypeB'=>$adB,'whA'=>2,'whB'=>1,'whAName'=>$t->sender->name??'','whBName'=>$t->receiver->name??'','flash'=>['success'=>session('success'),'error'=>session('errorMessage')??session('error')]]);
    }

    public function transactionSyncDisplay(Transaction $t): RedirectResponse { Gate::authorize(Jubelio::getPermissions()['sync']); $t->update(['sync_hide'=>$t->sync_hide=='N'?'Y':'N']); return back()->with('success','Updated.'); }

    public function adjustStok(Request $r, $id, AdjustStock $a): RedirectResponse
    {
        Gate::authorize(Jubelio::getPermissions()['sync']);
        $t = Transaction::with(['details.item'])->findOrFail($id);
        try { $res = $a->execute($t,(int)$r->side,(int)$r->adjustType,(int)$r->whType); return $res['success'] ? back()->with('success',$res['message']) : back()->with('errorMessage',$res['message']); }
        catch(\RuntimeException $e) { return back()->with('errorMessage',$e->getMessage()); }
    }

    private function syncCek(Transaction $i, array $sw): ?string
    {
        if(in_array($i->type,[TransactionType::Sell->value,TransactionType::ReturnSupplier->value])) return 'S';
        if(in_array($i->type,[TransactionType::Buy->value,TransactionType::Return->value])) return 'R';
        if($i->type==TransactionType::Move->value){$s=in_array($i->sender_id,$sw);$r=in_array($i->receiver_id,$sw);return match(true){$s&&$r=>'B',$s=>'S',$r=>'R',default=>null};}
        return null;
    }
}
