<?php

declare(strict_types=1);

namespace MiPress\Core\PathGenerators;

use Awcodes\Curator\PathGenerators\Contracts\PathGenerator;
use Carbon\Carbon;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(?string $baseDir = null): string
    {
        $now = Carbon::now();

        return sprintf('curatormedia/%s/%s', $now->format('Y'), $now->format('m'));
    }
}
