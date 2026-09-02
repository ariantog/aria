<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSellCashInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'account_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $accountId = (int) $this->input('account_id');
            if ($accountId < 1) {
                return;
            }

            $account = Addrbook::query()->find($accountId);
            if (! $account || (int) $account->type !== Addrbook::TYPE_BANK) {
                $validator->errors()->add('account_id', 'Select a bank account.');
            }
        });
    }
}
