<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profession;
use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'profession_id' => Profession::factory(),
            'blizzard_recipe_id' => fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->words(3, true),
            'crafted_item_id_silver' => null,
            'crafted_item_id_gold' => null,
            'crafted_quantity' => 1,
            'is_commodity' => true,
            'last_synced_at' => now(),
        ];
    }
}
