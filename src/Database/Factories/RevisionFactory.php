<?php

declare(strict_types=1);

namespace MiPress\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use MiPress\Core\Models\Revision;

class RevisionFactory extends Factory
{
    protected $model = Revision::class;

    public function definition(): array
    {
        return [
            'revisionable_type' => 'entry',
            'revisionable_id' => 1,
            'user_id' => null,
            'data' => [],
            'note' => null,
        ];
    }
}
