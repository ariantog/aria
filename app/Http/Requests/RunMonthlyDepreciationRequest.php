<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunMonthlyDepreciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2019', 'max:2100'],
            'expense_account_id' => ['required', 'integer', 'exists:customers,id'],
            'contra_account_id' => ['required', 'integer', 'exists:customers,id', 'different:expense_account_id'],
        ];
    }
}
