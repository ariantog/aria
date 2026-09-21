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
            'product_name' => ['nullable', 'string', 'max:255'],
            'pricing.price.scope' => ['nullable', 'in:size,colorway,group'],
            'pricing.price.value' => ['nullable', 'numeric', 'min:0'],
            'pricing.reseller_price.scope' => ['nullable', 'in:size,colorway,group'],
            'pricing.reseller_price.value' => ['nullable', 'numeric', 'min:0'],
            'pricing.cost.scope' => ['nullable', 'in:size,colorway,group'],
            'pricing.cost.value' => $isAsset
                ? ['required_without:cost', 'nullable', 'numeric', 'min:0.01']
                : ['nullable', 'numeric', 'min:0'],
            'pricing.cost_cnh.scope' => ['nullable', 'in:size,colorway,group'],
            'pricing.cost_cnh.value' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'cost' => $isAsset
                ? ['required_without:pricing.cost.value', 'nullable', 'numeric', 'min:0.01']
                : ['nullable', 'numeric', 'min:0'],
            'cost_cnh' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'description2' => ['nullable', 'string'],
            'reseller_price' => ['nullable', 'numeric', 'min:0'],
            'url' => ['nullable', 'string', 'max:255'],
            'restock_urgent_threshold' => ['nullable', 'integer', 'min:1'],
            'sku_overrides' => ['nullable', 'array'],
            'sku_overrides.*.price' => ['nullable', 'numeric', 'min:0'],
            'sku_overrides.*.cost' => ['nullable', 'numeric', 'min:0'],
            'sku_overrides.*.cost_cnh' => ['nullable', 'numeric', 'min:0'],
            'sku_overrides.*.reseller_price' => ['nullable', 'numeric', 'min:0'],
            'sku_overrides.*.description' => ['nullable', 'string'],
            'sku_overrides.*.description2' => ['nullable', 'string'],
            'sku_overrides.*.restock_urgent_threshold' => ['nullable', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:2048'],
            'tags.types' => $isAsset ? ['required', 'array', 'min:1'] : ['required'],
            'tags.sizes' => ['required', 'array', 'min:1'],
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
            'tags.types.min' => 'Please select a type for asset lancar.',
            'tags.jahit.required' => 'Please select a jahit tag.',
            'pricing.cost.value.required_without' => 'Cost price is required for asset lancar.',
            'pricing.cost.value.min' => 'Cost price is required for asset lancar.',
            'cost.required_without' => 'Cost price is required for asset lancar.',
            'cost.min' => 'Cost price is required for asset lancar.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
