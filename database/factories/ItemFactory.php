<?php

namespace Database\Factories;

use App\Enums\ItemBrand;
use App\Enums\ItemType;
use App\Models\ItemGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => ItemGroup::factory(),
            'name' => $this->faker->words(3, true),
            'code' => $this->faker->unique()->bothify('??#####??'),
            'pcode' => $this->faker->bothify('??#####/##'),
            'brand' => ItemBrand::NO_BRAND,
            'type' => ItemType::ITEM,
            'price' => $this->faker->randomFloat(2, 10000, 1000000),
            'cost' => $this->faker->randomFloat(2, 5000, 500000),
            'tag_ids' => '',
            'description' => $this->faker->sentence(),
        ];
    }
}
