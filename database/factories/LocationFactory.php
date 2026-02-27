<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->city(),
            'address' => $this->faker->address(),
            'description' => $this->faker->sentence(),
            'type' => 1, // Default type
        ];
    }

    public function warehouse(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => \App\Models\Addrbook::TYPE_WAREHOUSE,
            ];
        });
    }
}
