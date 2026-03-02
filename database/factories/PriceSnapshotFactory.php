<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\PriceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceSnapshotFactory extends Factory
{
    protected $model = PriceSnapshot::class;

    public function definition(): array
    {
        $avgPrice = $this->faker->numberBetween(10_000, 500_000); // copper

        return [
            'catalog_item_id' => CatalogItem::factory(),
            'min_price' => (int) ($avgPrice * 0.85),
            'avg_price' => $avgPrice,
            'median_price' => (int) ($avgPrice * 0.95),
            'total_volume' => $this->faker->numberBetween(100, 50_000),
            'polled_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
