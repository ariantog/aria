<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
        /** @var \App\Models\Setting|null $setting */
        $setting = $this->route('system_setting');

        return [
            'group' => 'required|string|max:255',
            'name' => 'required|string|max:255|unique:settings,name,'.($setting?->id ?? 'NULL'),
            'value' => 'nullable',
            'warehouse_ids' => 'nullable|array',
            'warehouse_ids.*' => 'integer|exists:customers,id',
        ];
    }
}
