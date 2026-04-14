<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class EntryFactory extends Factory
{
    protected $model = Entry::class;

    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'blueprint_id' => null,
            'title' => $this->faker->sentence(4),
            'slug' => null,
            'data' => [],
            'status' => ContentStatus::Draft,
            'published_at' => null,
            'author_id' => User::factory(),
            'sort_order' => 0,
            'origin_id' => null,
            'locale' => 'cs',
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => ContentStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(['status' => ContentStatus::Draft]);
    }
}
