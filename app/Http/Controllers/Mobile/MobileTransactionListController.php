<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\LocationAccessService;
use App\Services\TransactionListFilterService;
use App\Support\Mobile\MobileTransactionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MobileTransactionListController extends Controller
{
    public function __construct(
        protected TransactionListFilterService $listFilter,
        protected LocationAccessService $locationAccess,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(Transaction::getPermissions()['view']);

        $user = $request->user();
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');
        $perPage = $this->listFilter->resolvePerPage($request);

        $query = $this->listFilter->filteredTransactionsQuery($request, $user);
        if (in_array($sort, ['date', 'invoice', 'type', 'total', 'total_items'], true)) {
            $query->orderBy($sort, $direction)->orderBy('id', 'desc');
        } else {
            $query->orderBy('date', 'desc')->orderBy('id', 'desc');
        }

        $paginator = $query->paginate($perPage)->withQueryString();

        $data = $paginator->getCollection()
            ->map(fn (Transaction $transaction) => MobileTransactionPresenter::listRow($transaction, $user))
            ->values();

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        Gate::authorize(Transaction::getPermissions()['show']);
        abort_unless(
            $this->locationAccess->canAccessTransaction($request->user(), $transaction),
            403
        );

        $transaction->load(['details.item', 'sender', 'receiver']);
        $transaction->sortDetailsBySku();

        return response()->json([
            'transaction' => MobileTransactionPresenter::detail($transaction, $request->user()),
        ]);
    }
}
