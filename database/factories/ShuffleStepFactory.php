<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shuffle;
use App\Models\ShuffleStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShuffleStepFactory extends Factory
{
    protected $model = ShuffleStep::class;

    public function definition(): array
    {
        return [
            'shuffle_id' => Shuffle::factory(),
            'input_blizzard_item_id' => $this->faker->numberBetween(100000, 300000),
            'output_blizzard_item_id' => $this->faker->numberBetween(100000, 300000),
            'output_qty_min' => 1,
            'output_qty_max' => 1,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
