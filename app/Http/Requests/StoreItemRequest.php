<?php

namespace App\Http\Requests;

use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function rules(): array
    {
        $isAsset = (int) $this->input('type') === ItemType::ASSET_LANCAR->value;

        return [
            'pcode' => ['required', 'string'],
            'type' => ['required', 'integer'],
            'product_name' => $isAsset ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'description2' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
            'tags.types' => $isAsset ? ['nullable'] : ['required'],
            'tags.sizes' => ['required', 'array', 'min:1'],
            'cost' => $isAsset ? ['required', 'numeric'] : ['nullable'],
            'tags.warna' => $isAsset ? ['required', 'array', 'min:1'] : ['required'],
            'tags.jahit' => $isAsset ? ['nullable'] : ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_name.required' => 'Product name is required.',
            'tags.warna.required' => 'Please select at least one color (warna).',
            'tags.warna.min' => 'Please select at least one color (warna).',
            'tags.sizes.required' => 'Please select at least one size.',
            'tags.sizes.min' => 'Please select at least one size.',
            'tags.types.required' => 'Please select a type (SKU prefix).',
            'tags.jahit.required' => 'Please select a jahit tag.',
            'cost.required' => 'Cost price is required for asset lancar.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
