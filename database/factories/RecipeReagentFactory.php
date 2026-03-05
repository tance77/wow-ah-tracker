<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CatalogItem;
use App\Models\Recipe;
use App\Models\RecipeReagent;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeReagentFactory extends Factory
{
    protected $model = RecipeReagent::class;

    public function definition(): array
    {
        return [
            'recipe_id' => Recipe::factory(),
            'catalog_item_id' => CatalogItem::factory(),
            'quantity' => fake()->numberBetween(1, 20),
        ];
    }
}
