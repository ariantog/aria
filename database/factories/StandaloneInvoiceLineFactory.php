<?php

namespace Database\Factories;

use App\Models\StandaloneInvoice;
use App\Models\StandaloneInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandaloneInvoiceLine>
 */
class StandaloneInvoiceLineFactory extends Factory
{
    protected $model = StandaloneInvoiceLine::class;

    public function definition(): array
    {
        $qty = $this->faker->numberBetween(1, 10);
        $price = $this->faker->numberBetween(10_000, 500_000);

        return [
            'standalone_invoice_id' => StandaloneInvoice::factory(),
            'line_order' => 0,
            'description' => $this->faker->words(3, true),
            'quantity' => $qty,
            'price' => $price,
            'total' => $qty * $price,
        ];
    }
}
