<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJubelioStockCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'per_type_limit' => 'required|integer|min:10|max:100',
            'demand_days' => 'required|integer|min:7|max:365',
            'target_discrepancies' => 'nullable|integer|min:10|max:200',
        ];
    }
}
