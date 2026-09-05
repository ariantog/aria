<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Addrbook;
use App\Services\UserPreferenceService;
use App\Support\LikeSearch;
use App\Support\UserPreferenceRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class TransactionDefaultsController extends Controller
{
    public function __construct(
        protected UserPreferenceService $preferences,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $form = $this->preferences->transactionDefaultsFormData($user);
        $definitions = UserPreferenceRegistry::transactionDefaults();
        $fieldLookup = [
            'default_supplier_id' => 'supplier',
            'default_warehouse_id' => 'warehouse',
            'default_move_receiver_id' => 'move_receiver',
            'default_customer_id' => 'customer',
            'default_cash_in_bank_id' => 'bank',
            'default_cash_out_bank_id' => 'bank',
            'default_transfer_from_id' => 'transfer_account',
            'default_transfer_to_id' => 'transfer_account',
        ];

        $groups = [];
        foreach ($definitions as $slug => $definition) {
            $field = str_replace('transactions.', '', $slug);
            $groupName = $definition['group'];
            $groups[$groupName][] = [
                'field' => $field,
                'definition' => $definition,
                'initial' => $form['contacts'][$field]
                    ? ['id' => $form['contacts'][$field]->id, 'name' => $form['contacts'][$field]->name]
                    : null,
                'lookupType' => $fieldLookup[$field] ?? 'warehouse',
            ];
        }

        return view('settings.transaction-defaults', [
            'groups' => $groups,
            'lookupUrls' => [
                'supplier' => route('transaction-defaults.lookup', ['type' => 'supplier']),
                'warehouse' => route('transaction-defaults.lookup', ['type' => 'warehouse']),
                'move_receiver' => route('transaction-defaults.lookup', ['type' => 'move_receiver']),
                'customer' => route('transaction-defaults.lookup', ['type' => 'customer']),
                'bank' => route('transaction-defaults.lookup', ['type' => 'bank']),
                'transfer_account' => route('transaction-defaults.lookup', ['type' => 'transfer_account']),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_supplier_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_warehouse_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_move_receiver_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_cash_in_bank_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_cash_out_bank_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_transfer_from_id' => ['nullable', 'integer', 'exists:customers,id'],
            'default_transfer_to_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        try {
            $this->preferences->updateTransactionDefaults($request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('transaction-defaults.edit')
            ->with('success', 'Transaction defaults saved.');
    }

    public function lookup(Request $request, string $type)
    {
        abort_unless(in_array($type, UserPreferenceRegistry::lookupTypes(), true), 404);

        $types = UserPreferenceRegistry::lookupAddrbookTypes($type);
        abort_if($types === [], 404);

        $query = Addrbook::query()->whereIn('type', $types);
        app(\App\Services\LocationAccessService::class)->applyAddrbookScope($query, $request->user());

        if ($search = $request->input('search')) {
            $pattern = LikeSearch::contains($search);
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'like', $pattern)
                    ->orWhere('id', 'like', $pattern);
            });
        }

        return response()->json(
            $query->orderBy('name')->limit(20)->get(['id', 'name'])
        );
    }
}
