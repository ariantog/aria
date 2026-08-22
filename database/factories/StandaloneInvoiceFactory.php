<?php

namespace Database\Factories;

use App\Models\StandaloneInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StandaloneInvoice>
 */
class StandaloneInvoiceFactory extends Factory
{
    protected $model = StandaloneInvoice::class;

    public function definition(): array
    {
        return [
            'number' => StandaloneInvoice::generateNumber(),
            'date' => now()->toDateString(),
            'recipient' => $this->faker->company(),
            'template' => StandaloneInvoice::TEMPLATE_CLASSIC,
            'terms_of_payment' => "Pembayaran lunas.\nHarga belum termasuk PPN.",
            'pay_to' => "BCA\n1234567890\nCV TEST",
            'signatory_name' => 'Test Signatory',
            'total_qty' => 1,
            'subtotal' => 100_000,
            'user_id' => User::factory(),
        ];
    }
}
