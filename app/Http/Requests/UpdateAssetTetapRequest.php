<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetTetapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'warehouse_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
