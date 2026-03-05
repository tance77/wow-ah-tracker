<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ShuffleStep;
use App\Models\ShuffleStepByproduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShuffleStepByproductFactory extends Factory
{
    protected $model = ShuffleStepByproduct::class;

    public function definition(): array
    {
        return [
            'shuffle_step_id' => ShuffleStep::factory(),
            'blizzard_item_id' => $this->faker->numberBetween(100000, 300000),
            'item_name' => $this->faker->word(),
            'chance_percent' => 20.00,
            'quantity' => 1,
        ];
    }
}
