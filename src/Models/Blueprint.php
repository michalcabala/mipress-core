<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MiPress\Core\Database\Factories\BlueprintFactory;

class Blueprint extends Model
{
    use HasFactory;

    protected $table = 'blueprints';

    protected $fillable = [
        'name',
        'handle',
        'fields',
    ];

    protected $casts = [
        'fields' => 'array',
    ];

    protected $attributes = [
        'fields' => '[]',
    ];

    protected static function newFactory(): BlueprintFactory
    {
        return BlueprintFactory::new();
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }
}
