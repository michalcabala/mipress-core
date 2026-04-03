<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\TermResource\Pages;

use Filament\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;
use MiPress\Core\Filament\Resources\TermResource;
use MiPress\Core\Models\Taxonomy;
use Openplain\FilamentTreeView\Resources\Pages\TreePage;
use Openplain\FilamentTreeView\Tree;

class ListTerms extends TreePage
{
    protected static string $resource = TermResource::class;

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

    public function tree(Tree $tree): Tree
    {
        return parent::tree($tree)
            ->query(function (): Builder {
                $query = static::getResource()::getEloquentQuery();
                $taxonomy = $this->resolveTaxonomy();

                if ($taxonomy) {
                    $query->where('taxonomy_id', $taxonomy->getKey());
                }

                return $query;
            })
            ->maxDepth(fn (): ?int => $this->resolveTaxonomy()?->is_hierarchical ? null : 1);
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
