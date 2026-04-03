<?php

declare(strict_types=1);

namespace MiPress\Core\Mason;

use MiPress\Core\Mason\Bricks\CallToActionBrick;
use MiPress\Core\Mason\Bricks\InsightGridBrick;
use MiPress\Core\Mason\Bricks\NarrativeBrick;
use MiPress\Core\Mason\Bricks\PullQuoteBrick;

class EditorialBrickCollection
{
    /**
     * @return array<class-string>
     */
    public static function make(): array
    {
        $bricks = [
            NarrativeBrick::class,
            PullQuoteBrick::class,
            InsightGridBrick::class,
            CallToActionBrick::class,
        ];

        $externalBricks = [];

        if (app()->bound('mipress.forms.mason.bricks')) {
            $externalBricks = (array) app('mipress.forms.mason.bricks');
        }

        return array_values(array_unique([...$bricks, ...$externalBricks]));
    }
}
