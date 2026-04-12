<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Media\MediaConfig;
use MiPress\Core\Media\RegistersMiPressMediaConversions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Attachment extends Model implements HasMedia
{
    use InteractsWithMedia;
    use RegistersMiPressMediaConversions {
        RegistersMiPressMediaConversions::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $table = 'attachments';

    protected $fillable = [
        'name',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaConfig::libraryCollection())
            ->singleFile()
            ->useDisk(MediaConfig::disk())
            ->acceptsMimeTypes(MediaConfig::allowedMimeTypes());
    }

    public function mediaItem(): ?Media
    {
        /** @var Media|null $media */
        $media = $this->getFirstMedia(MediaConfig::libraryCollection());

        return $media;
    }
}
