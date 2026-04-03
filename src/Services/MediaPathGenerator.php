<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use Awcodes\Curator\PathGenerators\Contracts\PathGenerator;
use Carbon\Carbon;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(?string $baseDir = null): string
    {
        $now = Carbon::now();
        $rootDirectory = blank($baseDir) ? 'media' : trim($baseDir, '/');

        $datePath = sprintf('%s/%s/%s', $now->format('Y'), $now->format('m'), $now->format('d'));

        return $rootDirectory.'/'.$datePath;
    }
}
