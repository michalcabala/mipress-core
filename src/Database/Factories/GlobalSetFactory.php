<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Models\GlobalSet;

class GlobalSetFactory extends Factory
{
    protected $model = GlobalSet::class;

    public function definition(): array
    {
        return [
            'handle' => $this->faker->unique()->slug(1),
            'title' => $this->faker->words(2, true),
            'data' => [],
        ];
    }
}
