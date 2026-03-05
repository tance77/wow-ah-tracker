<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profession;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfessionFactory extends Factory
{
    protected $model = Profession::class;

    public function definition(): array
    {
        return [
            'blizzard_profession_id' => fake()->unique()->numberBetween(100, 999),
            'name' => fake()->word(),
            'icon_url' => fake()->imageUrl(),
            'last_synced_at' => now(),
        ];
    }
}
