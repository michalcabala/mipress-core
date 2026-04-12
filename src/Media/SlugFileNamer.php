<?php

declare(strict_types=1);

namespace MiPress\Core\Media;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Support\FileNamer\FileNamer;

class SlugFileNamer extends FileNamer
{
    public function originalFileName(string $fileName): string
    {
        $baseName = parent::originalFileName($fileName);
        $slug = Str::slug($baseName, '-');

        return $slug !== '' ? $slug : 'soubor';
    }

    public function conversionFileName(string $fileName, Conversion $conversion): string
    {
        return $this->originalFileName($fileName).'-'.$conversion->getName();
    }

    public function responsiveFileName(string $fileName): string
    {
        return $this->originalFileName($fileName);
    }
}
