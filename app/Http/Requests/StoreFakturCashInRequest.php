<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use App\Models\TaxFakturImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFakturCashInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'integer', 'exists:customers,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'variance_expense_addrbook_id' => ['nullable', 'integer', 'exists:customers,id'],
            'variance_record_ppn' => ['sometimes', 'boolean'],
            'variance_record_pph' => ['sometimes', 'boolean'],
            'variance_ppn_dpp' => ['nullable', 'numeric', 'min:0'],
            'variance_ppn' => ['nullable', 'numeric', 'min:0'],
            'variance_pph' => ['nullable', 'numeric', 'min:0'],
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
                $validator->errors()->add('account_id', 'Pilih akun bank.');
            }

            $expenseId = (int) $this->input('variance_expense_addrbook_id');
            if ($expenseId > 0) {
                $expense = Addrbook::query()->find($expenseId);
                if (! $expense || (int) $expense->type !== Addrbook::TYPE_ACCOUNT) {
                    $validator->errors()->add('variance_expense_addrbook_id', 'Akun biaya selisih harus akun ledger.');
                }
            }

            $recordPpn = filter_var($this->input('variance_record_ppn', false), FILTER_VALIDATE_BOOLEAN);
            if (! $recordPpn) {
                return;
            }

            /** @var TaxFakturImport|null $import */
            $import = $this->route('import');
            if (! $import?->reportingEntity?->is_pkp) {
                $validator->errors()->add('variance_record_ppn', 'PPN selisih hanya untuk entitas PKP.');
            }
        });
    }
}
