<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use App\Models\ReportingEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1', 'max:7'],
            'items.*.customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->whereIn('type', Addrbook::cashPartyTypes())),
            ],
            'items.*.invoice' => ['nullable', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
            'items.*.total' => ['required', 'numeric', 'min:0.01'],
            'items.*.record_ppn' => ['sometimes', 'boolean'],
            'items.*.ppn_dpp' => ['nullable', 'numeric', 'min:0'],
            'items.*.ppn' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $accountId = (int) $this->input('account_id');
            $entity = ReportingEntity::findActiveForBank($accountId);
            $isPkpBank = $entity?->is_pkp ?? false;

            foreach ($this->input('items', []) as $index => $item) {
                $recordPpn = filter_var($item['record_ppn'] ?? false, FILTER_VALIDATE_BOOLEAN);

                if (! $recordPpn) {
                    continue;
                }

                if (! $isPkpBank) {
                    $validator->errors()->add(
                        "items.{$index}.record_ppn",
                        'PPN can only be recorded when the bank account belongs to a PKP reporting entity.',
                    );
                }
            }
        });
    }

    public function authorize(): bool { return true; }
}
