<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'sender' => ['required', 'integer', 'exists:customers,id'],
            'receiver' => ['required', 'integer', 'exists:customers,id', 'different:sender'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'total' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function authorize(): bool { return true; }
}
