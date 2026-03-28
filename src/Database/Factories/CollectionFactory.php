<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Collection;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'handle' => $this->faker->unique()->slug(2),
            'blueprint_id' => Blueprint::factory(),
            'icon' => 'far-file-lines',
            'route' => null,
            'dated' => false,
            'slugs' => true,
            'sort_direction' => 'asc',
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
