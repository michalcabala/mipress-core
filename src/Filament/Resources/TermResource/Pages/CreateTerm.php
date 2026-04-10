<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Filament\Resources\TermResource;

class CreateTerm extends CreateRecord
{
    protected static string $resource = TermResource::class;

    public string $taxonomyHandle = '';

    public function mount(?string $taxonomy = null): void
    {
        if (blank($this->taxonomyHandle)) {
            $this->taxonomyHandle = $taxonomy ?: (string) request()->query('taxonomy', request()->query('taxonomy_id', ''));
        }

        parent::mount();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index', [
            'taxonomy' => $this->taxonomyHandle ?: null,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (filled($data['taxonomy_id'] ?? null)) {
            return $data;
        }

        if (! filled($this->taxonomyHandle)) {
            return $data;
        }

        $taxonomy = TermResource::resolveTaxonomy($this->taxonomyHandle);

        if ($taxonomy) {
            $data['taxonomy_id'] = $taxonomy->getKey();
        }

        return $data;
    }
}
