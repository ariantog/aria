    public function itemTransactions(Request $request, Item $item)
    {
        Gate::authorize(Item::getPermissions()['view']);

        $transactions = TransactionDetail::with(['transaction.sender', 'transaction.receiver'])
            ->where('item_id', $item->id)
            ->whereHas('transaction')
            ->orderBy('transaction_id', 'desc')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Items/ItemTransactions', [
            'item' => $item->load('group'),
            'transactions' => $transactions,
        ]);
    }