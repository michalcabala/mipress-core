<?php

declare(strict_types=1);

namespace MiPress\Core\Theme;

use InvalidArgumentException;

final readonly class ThemeManifest
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $version,
        public string $author,
        public ?string $description,
        public ?string $screenshot,
        public string $path,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $path = ''): static
    {
        foreach (['name', 'slug', 'version'] as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Theme manifest missing required field: {$field}");
            }
        }

        return new self(
            name: $data['name'],
            slug: $data['slug'],
            version: $data['version'],
            author: $data['author'] ?? '',
            description: $data['description'] ?? null,
            screenshot: $data['screenshot'] ?? null,
            path: $path,
        );
    }
}
