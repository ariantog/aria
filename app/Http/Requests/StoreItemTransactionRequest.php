<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreItemTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        $creatingCashIn = $this->boolean('create_cash_in');

        return [
            'date' => ['required', 'date'],
            'due' => ['nullable', 'date'],
            'type' => ['required', 'string', Rule::in(array_keys(config('transaction_rules', [])))],
            'sender_id' => ['required', 'integer', 'exists:customers,id'],
            'receiver_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'adjustment' => ['nullable', 'numeric'],
            'ppn_included' => ['sometimes', 'boolean'],
            'create_cash_in' => ['sometimes', 'boolean'],
            'cash_in_amount' => [$creatingCashIn ? 'required' : 'nullable', 'numeric', 'min:0.01'],
            'cash_in_account_id' => [$creatingCashIn ? 'required' : 'nullable', 'integer', 'exists:customers,id'],
            'cash_in_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.item_id.required' => 'Each item must be selected.',
            'items.*.item_id.exists' => 'One or more selected items do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be greater than 0.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('create_cash_in')) {
                return;
            }

            if ($this->input('type') !== 'sell') {
                $validator->errors()->add('create_cash_in', 'Cash in can only be created with a sell transaction.');

                return;
            }

            $accountId = (int) $this->input('cash_in_account_id');
            if ($accountId < 1) {
                return;
            }

            $account = Addrbook::query()->find($accountId);
            if (! $account || (int) $account->type !== Addrbook::TYPE_BANK) {
                $validator->errors()->add('cash_in_account_id', 'Select a bank account.');
            }
        });
    }

    public function wantsCashIn(): bool
    {
        return $this->boolean('create_cash_in');
    }

    /**
     * @return array{date: string|null, account_id: int, amount: float}|null
     */
    public function cashInPayload(): ?array
    {
        if (! $this->wantsCashIn()) {
            return null;
        }

        $data = $this->validated();

        return [
            'date' => $data['cash_in_date'] ?? null,
            'account_id' => (int) $data['cash_in_account_id'],
            'amount' => (float) $data['cash_in_amount'],
        ];
    }

    public function validatedType(): int
    {
        $config = config("transaction_rules.{$this->validated('type')}");

        return (int) $config['id'];
    }
}
