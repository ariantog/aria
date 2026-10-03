<?php

namespace Database\Factories;

use App\Models\Addrbook;
use App\Models\Shopeesync;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shopeesync>
 */
class ShopeesyncFactory extends Factory
{
    protected $model = Shopeesync::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => fn () => Addrbook::factory()->warehouse()->create()->id,
            'shop_id' => 0,
            'shopee_location_id' => 'IDZ',
            'shopee_warehouse_id' => 6,
            'shopee_warehouse_name' => 'Pickup Warehouse',
        ];
    }
}
