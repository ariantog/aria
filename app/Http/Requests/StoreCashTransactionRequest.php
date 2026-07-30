<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashTransactionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'account_id' => ['required', 'integer', 'exists:addrbooks,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.customer_id' => ['required', 'integer', 'exists:addrbooks,id'],
            'items.*.invoice_number' => ['nullable', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string', 'max:5000'],
            'items.*.total' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function authorize(): bool { return true; }
}
