<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use App\Models\User;
use Awcodes\Curator\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuratorMedia extends Media
{
    protected $fillable = [
        'disk',
        'directory',
        'visibility',
        'name',
        'path',
        'width',
        'height',
        'size',
        'type',
        'ext',
        'alt',
        'title',
        'description',
        'caption',
        'exif',
        'curations',
        'focal_point_x',
        'focal_point_y',
        'file',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'curations' => 'array',
        'exif' => 'array',
        'focal_point_x' => 'integer',
        'focal_point_y' => 'integer',
    ];

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
