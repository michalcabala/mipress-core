<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Models\Blueprint;

class BlueprintFactory extends Factory
{
    protected $model = Blueprint::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'handle' => $this->faker->unique()->slug(2),
            'fields' => [],
        ];
    }

    public function withFields(array $fields): static
    {
        return $this->state(['fields' => $fields]);
    }
}
