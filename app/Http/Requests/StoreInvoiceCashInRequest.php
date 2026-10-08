<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvoiceCashInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'sender_id' => ['required', 'integer', 'exists:customers,id'],
            'account_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $senderId = (int) $this->input('sender_id');
            if ($senderId > 0) {
                $sender = Addrbook::query()->find($senderId);
                if (! $sender || ! in_array((int) $sender->type, Addrbook::cashPartyTypes(), true)) {
                    $validator->errors()->add('sender_id', 'Select a customer, reseller, supplier, or ledger account.');
                }
            }

            $accountId = (int) $this->input('account_id');
            if ($accountId > 0) {
                $account = Addrbook::query()->find($accountId);
                if (! $account || (int) $account->type !== Addrbook::TYPE_BANK) {
                    $validator->errors()->add('account_id', 'Select a bank account.');
                }
            }
        });
    }
}
