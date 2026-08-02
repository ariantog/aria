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
        ];
    }

    public function warehouse(): Factory
    {
        // The locations table only stores a name; the "warehouse" concept
        // lives on the Addrbook model. Kept as a no-op state so existing
        // callers using ->warehouse() continue to work.
        return $this->state(fn (array $attributes) => []);
    }
}
