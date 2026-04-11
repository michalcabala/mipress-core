<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HasCreateWorkflowActions;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\HierarchyParentResolver;

class CreatePage extends CreateRecord
{
    use HasCreateWorkflowActions;

    protected static string $resource = PageResource::class;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $blueprint = Blueprint::where('handle', 'page')->first();

        if ($blueprint) {
            $data['blueprint_id'] = $blueprint->id;
        }

        $data['parent_id'] = $this->resolveParentId($data);

        return $this->workflowTransitions()->prepareCreateDataForIntent($data, $this->workflowCreateIntent());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data): ?int
    {
        return app(HierarchyParentResolver::class)->resolvePageParentForCreate(
            data_get($data, 'parent_id'),
        );
    }

    public function getTitle(): string
    {
        return 'Nová stránka';
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof Page || $record->status !== EntryStatus::InReview) {
            return;
        }

        $this->workflowNotifications()->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: 'Nová stránka ke schválení',
            body: 'Stránka "'.$record->title.'" čeká na schválení publikace.',
            editUrl: PageResource::getUrl('edit', ['record' => $record]),
            previewRouteName: 'preview.page',
            previewRouteParameterName: 'page',
        );
    }
}
