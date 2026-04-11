<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Schemas\Schema;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\EntryResource\Schemas\EntrySeoForm;
use MiPress\Core\Services\WorkflowTransitionService;

class EditEntrySeo extends EditEntry
{
    use UsesCurrentPageSubNavigation;

    protected static ?string $title = 'SEO';

    protected static ?string $breadcrumb = 'SEO';

    protected static ?string $navigationLabel = 'SEO';

    protected static string|\BackedEnum|null $navigationIcon = 'far-magnifying-glass';

    public function form(Schema $schema): Schema
    {
        return EntrySeoForm::configure($schema);
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

    protected function afterSave(): void {}
}
