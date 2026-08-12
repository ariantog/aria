<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduksiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'potong_id' => 'required|exists:prod_worker,id',
            'surat_jalan_potong' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.size_id' => 'required|exists:tags,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.customer' => 'nullable|string',
            'items.*.warna' => 'nullable|string',
        ];
    }
}
