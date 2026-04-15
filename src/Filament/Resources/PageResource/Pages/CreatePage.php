<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\Concerns\HasCreateWorkflowActions;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class CreatePage extends CreateRecord
{
    use HasContextualCrudNotifications;
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

        return app(WorkflowTransitionService::class)->prepareCreateDataForIntent(
            $data,
            $this->workflowCreateIntent(),
        );
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
        return __('mipress::admin.resources.page.pages.create_title');
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof Page || $record->status !== ContentStatus::InReview) {
            return;
        }

        app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
            record: $record,
            permission: 'entry.publish',
            title: __('mipress::admin.resources.page.workflow.review_request_title'),
            body: __('mipress::admin.resources.page.workflow.review_request_body', ['title' => $record->title]),
            editUrl: PageResource::getUrl('edit', ['record' => $record]),
            previewRouteName: 'preview.page',
            previewRouteParameterName: 'page',
        );
    }
}
