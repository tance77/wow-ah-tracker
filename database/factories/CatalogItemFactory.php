<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    public function definition(): array
    {
        return [
            'blizzard_item_id' => $this->faker->numberBetween(100000, 300000),
            'name' => $this->faker->words(2, true),
            'category' => $this->faker->randomElement(['herb', 'ore', 'cloth', 'leather', 'enchanting', 'gem']),
        ];
    }
}
