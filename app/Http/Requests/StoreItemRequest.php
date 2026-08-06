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
            'price' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
            'description2' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:2048'],
            'tags.types' => ['required'],
            'tags.sizes' => ['required', 'array'],
            'name' => $isAsset ? ['required', 'string'] : ['nullable'],
            'cost' => $isAsset ? ['required', 'numeric'] : ['nullable'],
            'tags.warna' => $isAsset ? ['required', 'array'] : ['required'],
            'tags.jahit' => $isAsset ? ['nullable'] : ['required'],
            'alias' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
