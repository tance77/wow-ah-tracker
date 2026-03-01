<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WatchedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class WatchedItemFactory extends Factory
{
    protected $model = WatchedItem::class;

    public function definition(): array
    {
        return [
            'blizzard_item_id' => $this->faker->numberBetween(100, 200000),
            'name' => $this->faker->words(2, true),
            'buy_threshold' => $this->faker->numberBetween(5, 25),
            'sell_threshold' => $this->faker->numberBetween(5, 25),
        ];
    }
}
