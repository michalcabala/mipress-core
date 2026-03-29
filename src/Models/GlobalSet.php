<?php

declare(strict_types=1);

namespace MiPress\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Database\Factories\GlobalSetFactory;

class GlobalSet extends Model
{
    use HasFactory;

    protected $table = 'global_sets';

    protected $fillable = [
        'handle',
        'title',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
    ];

    protected static function newFactory(): GlobalSetFactory
    {
        return GlobalSetFactory::new();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->data, $key, $default);
    }

    public function set(string $key, mixed $value): static
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        $this->data = $data;

        return $this;
    }
}
