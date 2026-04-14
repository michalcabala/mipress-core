<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Models\Page;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'blueprint_id' => null,
            'title' => $this->faker->sentence(4),
            'slug' => null,
            'data' => [],
            'status' => ContentStatus::Draft,
            'published_at' => null,
            'author_id' => User::factory(),
            'sort_order' => 0,
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
