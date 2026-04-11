<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\PageResource\Schemas\PageSeoForm;
use MiPress\Core\Services\WorkflowTransitionService;

class EditPageSeo extends EditPage
{
    protected static ?string $title = 'SEO';

    protected static ?string $breadcrumb = 'SEO';

    protected static ?string $navigationLabel = 'SEO';

    public function form(Schema $schema): Schema
    {
        return PageSeoForm::configure($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->workflowIntent === 'review') {
            $data = app(WorkflowTransitionService::class)->prepareReviewData($data);
        }

        return $data;
    }
}
