<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'date' => Carbon::today()->format('Y-m-d'),
            'type' => Transaction::TYPE_BUY,
            'invoice' => $this->faker->unique()->numerify('INV-####'),
            'total' => 0, // Should be calculated or defined explicitly
            'discount' => 0,
            'ppn' => 0,
            'total_items' => 0,
            'sender_balance' => 0,
            'receiver_balance' => 0,
            'status' => Transaction::STATUS_COMPLETED,
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
