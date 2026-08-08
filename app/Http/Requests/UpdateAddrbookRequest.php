<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddrbookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'integer'],
            'address' => 'nullable|string',
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => 'nullable|email|max:255',
            'contact_person' => ['nullable', 'string', 'max:255'],
            'is_online' => 'boolean',
            'arrangement_enabled' => 'boolean',
            'arrangement_source_ids' => 'nullable|array',
            'arrangement_source_ids.*' => 'integer|exists:addrbooks,id',
            'ppn' => 'boolean',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
        ];
    }
}
