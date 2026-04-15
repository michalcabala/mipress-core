<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Carbon\CarbonInterface;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\EntryTaxonomySyncService;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class EditEntry extends EditRecord
{
    use HandlesWorkflowValidationErrors;
    use HasContextualCrudNotifications;
    use HasWorkflowActions;
    use UsesCurrentPageSubNavigation;

    protected static string $resource = EntryResource::class;

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected ?ContentStatus $statusBeforeSave = null;

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.common.edit');
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('edit', [
            'record' => $this->getRecord(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSubNavigationParameters(): array
    {
        return [
            ...parent::getSubNavigationParameters(),
            'currentPageClass' => static::class,
        ];
    }

    /**
     * @return array<string>
     */
    public function getResourceBreadcrumbs(): array
    {
        $collection = $this->getRecord()->collection;

        if ($collection === null) {
            return parent::getResourceBreadcrumbs();
        }

        $collectionHandle = EntryResource::normalizeCollectionHandle($collection->handle);

        if ($collectionHandle === null) {
            return parent::getResourceBreadcrumbs();
        }

        $breadcrumbs = [
            static::getResource()::getUrl('index', static::getResource()::collectionUrlParameters($collectionHandle)) => $collection->name,
        ];

        if (filled($cluster = static::getCluster())) {
            return $cluster::unshiftClusterBreadcrumbs($breadcrumbs);
        }

        return $breadcrumbs;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if ($record instanceof Entry) {
            $this->statusBeforeSave = $record->status;
        }

        if (
            $record instanceof Entry
            && $user?->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && in_array($record->status, [ContentStatus::Published, ContentStatus::InReview, ContentStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        $data['parent_id'] = $this->resolveParentId($data);

        if ($this->workflowIntent === 'publish') {
            return $data;
        }

        return app(WorkflowTransitionService::class)->prepareFormDataForStatus(
            $data,
            canPublish: (bool) $user?->can('publish', $record),
            currentStatus: $record instanceof Entry ? $record->status : null,
        );
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry) {
            return;
        }

        app(EntryTaxonomySyncService::class)->syncFromFormState($record, $this->form->getRawState());

        if ($this->statusBeforeSave !== null && $record->status !== $this->statusBeforeSave) {
            if ($record->status === ContentStatus::InReview) {
                app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
                    record: $record,
                    permission: 'entry.publish',
                    title: __('mipress::admin.resources.entry.workflow.review_request_title'),
                    body: __('mipress::admin.resources.entry.workflow.review_request_body', ['title' => $record->title]),
                    editUrl: EntryResource::getUrl('edit', [
                        'record' => $record,
                        ...EntryResource::collectionUrlParameters($record->collection?->handle),
                    ]),
                    previewRouteName: 'preview.entry',
                    previewRouteParameterName: 'entry',
                );
            }
        }

        $this->statusBeforeSave = $record->status;
    }

    protected function workflowRecordClass(): string
    {
        return Entry::class;
    }

    protected function workflowPublishActionName(): string
    {
        return 'publishEntry';
    }

    protected function workflowRejectActionName(): string
    {
        return 'rejectEntry';
    }

    protected function workflowUpdateActionName(): string
    {
        return 'updateEntry';
    }

    protected function workflowPublishedNotificationTitle(): string
    {
        return __('mipress::admin.resources.entry.workflow.published_title');
    }

    protected function workflowRejectedNotificationTitle(): string
    {
        return __('mipress::admin.resources.entry.workflow.rejected_title');
    }

    protected function workflowScheduledNotificationBody(CarbonInterface $scheduleAt): string
    {
        return __('mipress::admin.resources.entry.workflow.scheduled_body', ['date' => $scheduleAt->format('j. n. Y H:i')]);
    }

    protected function workflowReviewNotificationTitle(): string
    {
        return __('mipress::admin.resources.entry.workflow.review_request_title');
    }

    protected function workflowReviewNotificationBody(Model $record): string
    {
        if (! $record instanceof Entry) {
            return __('mipress::admin.resources.entry.workflow.review_fallback_body');
        }

        return __('mipress::admin.resources.entry.workflow.review_request_body', ['title' => $record->title]);
    }

    protected function workflowPreviewRouteName(): string
    {
        return 'preview.entry';
    }

    protected function workflowPreviewRouteParameterName(): string
    {
        return 'entry';
    }

    protected function workflowEditUrl(Model $record): string
    {
        $collection = $record instanceof Entry ? $record->collection : null;

        return EntryResource::getUrl('edit', [
            'record' => $record,
            ...EntryResource::collectionUrlParameters($collection?->handle),
        ]);
    }

    protected function workflowCompletedRedirectUrl(): string
    {
        $record = $this->getRecord();
        $collection = $record instanceof Entry ? $record->collection : null;

        return EntryResource::getUrl('index', EntryResource::collectionUrlParameters($collection?->handle));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data): ?int
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry || ! $record->collection?->hierarchical) {
            return null;
        }

        return app(HierarchyParentResolver::class)->resolveEntryParentForEdit(
            $record,
            data_get($data, 'parent_id'),
        );
    }
}
