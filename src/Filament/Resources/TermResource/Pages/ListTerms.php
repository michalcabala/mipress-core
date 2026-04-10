<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\TermResource;
use MiPress\Core\Models\Taxonomy;

class ListTerms extends ListRecords
{
    protected static string $resource = TermResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public string $taxonomyHandle = '';

    private bool $hasResolvedTaxonomy = false;

    private ?Taxonomy $resolvedTaxonomy = null;

    public function mount(?string $taxonomy = null): void
    {
        if (blank($this->taxonomyHandle)) {
            $this->taxonomyHandle = $taxonomy ?: (string) request()->query('taxonomy', request()->query('taxonomy_id', ''));
        }

        parent::mount();
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(function (Builder $query): void {
                $taxonomy = $this->resolveTaxonomy();

                if ($taxonomy) {
                    $query->where('taxonomy_id', $taxonomy->getKey());
                }
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create', [
                    'taxonomy' => $this->taxonomyHandle ?: null,
                ])),
        ];
    }

    public function getTitle(): string
    {
        $taxonomy = $this->resolveTaxonomy();

        if ($taxonomy) {
            return $taxonomy->title;
        }

        return 'Štítky';
    }

    private function resolveTaxonomy(): ?Taxonomy
    {
        if ($this->hasResolvedTaxonomy) {
            return $this->resolvedTaxonomy;
        }

        $this->hasResolvedTaxonomy = true;

        $taxonomy = TermResource::getCurrentTaxonomy();

        if ($taxonomy) {
            $this->resolvedTaxonomy = $taxonomy;

            return $this->resolvedTaxonomy;
        }

        if (blank($this->taxonomyHandle)) {
            return null;
        }

        $this->resolvedTaxonomy = is_numeric($this->taxonomyHandle)
            ? Taxonomy::find((int) $this->taxonomyHandle)
            : Taxonomy::where('handle', $this->taxonomyHandle)->first();

        return $this->resolvedTaxonomy;
    }
}
