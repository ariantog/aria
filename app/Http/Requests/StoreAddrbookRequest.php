<?php

namespace App\Http\Requests;

use App\Enums\ReportingLedgerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAddrbookRequest extends FormRequest
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
            'type' => ['required', 'integer'], // No longer validation exists:addrbook_types,id
            'name' => ['required', 'string', 'max:255'],
            'address' => 'nullable|string',
            'description' => 'nullable|string|max:2000',
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => 'nullable|email|max:255', // Removed unique check for now, or should be unique:customers,email
            'contact_person' => ['nullable', 'string', 'max:255'],
            'is_online' => 'boolean',
            'arrangement_enabled' => 'boolean',
            'arrangement_source_ids' => 'nullable|array',
            'arrangement_source_ids.*' => 'integer|exists:customers,id',
            'ppn' => 'boolean',
            'initial_balance' => 'nullable|numeric',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'npwp' => ['nullable', 'string', 'max:20'],
            'operation_id' => ['nullable', 'integer', 'exists:operations,id'],
            'ledger_hint' => ['nullable', 'string', 'max:2000'],
            'ledger_role' => ['nullable', 'string', Rule::enum(ReportingLedgerRole::class)],
            'reporting_role' => ['nullable', 'string', 'max:30'],
            'is_internal_lending' => ['boolean'],
            'is_active_in_reports' => ['boolean'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'payment_grace_days' => ['nullable', 'integer', 'min:0', 'max:60'],
        ];
    }
}
