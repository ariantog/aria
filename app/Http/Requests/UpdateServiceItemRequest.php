<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'description2' => ['nullable', 'string'],
            'tanpa_stok' => ['sometimes', 'boolean'],
            'allow_decimal_quantity' => ['sometimes', 'boolean'],
        ];
    }
}
