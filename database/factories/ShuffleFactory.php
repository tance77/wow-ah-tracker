<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shuffle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShuffleFactory extends Factory
{
    protected $model = Shuffle::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
        ];
    }
}
