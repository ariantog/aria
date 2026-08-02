<?php

namespace Database\Factories;

use App\Models\Addrbook;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddrbookFactory extends Factory
{
    protected $model = Addrbook::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'type' => Addrbook::TYPE_SUPPLIER, // Default to Supplier for tests
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
        ];
    }

    public function warehouse(): static
    {
        return $this->state(fn () => ['type' => Addrbook::TYPE_WAREHOUSE]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['type' => Addrbook::TYPE_CUSTOMER]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['type' => Addrbook::TYPE_SUPPLIER]);
    }
}
