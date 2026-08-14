<?php

namespace App\Http\Requests;

use App\Models\Addrbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function rules(): array
    {
        $accountTypes = Addrbook::transferAccountTypes();

        return [
            'date' => ['required', 'date'],
            'sender' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $q->whereIn('type', $accountTypes))],
            'receiver' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn ($q) => $q->whereIn('type', $accountTypes)), 'different:sender'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'total' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function authorize(): bool { return true; }
}
