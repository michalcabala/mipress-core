<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Carbon\CarbonInterface;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\ContentStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class EditPage extends EditRecord
{
    use HandlesWorkflowValidationErrors;
    use HasContextualCrudNotifications;
    use HasWorkflowActions;
    use UsesCurrentPageSubNavigation;

    protected static string $resource = PageResource::class;

    protected static ?string $navigationLabel = null;

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected ?ContentStatus $statusBeforeSave = null;

    public static function getNavigationLabel(): string
    {
        return __('mipress::admin.resources.page.edit_navigation_label');
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

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        $user = auth()->user();

        if ($record instanceof Page) {
            $this->statusBeforeSave = $record->status;
        }

        if (
            $record instanceof Page
            && $user?->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && in_array($record->status, [ContentStatus::Published, ContentStatus::InReview, ContentStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $this->resolveParentId($data);
        }

        if (! $this->shouldPrepareWorkflowData($data)) {
            return $data;
        }

        return app(WorkflowTransitionService::class)->prepareFormDataForStatus(
            $data,
            canPublish: (bool) $user?->can('publish', $record),
            currentStatus: $record instanceof Page ? $record->status : null,
        );
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof Page) {
            return;
        }

        if (! $this->shouldProcessStatusChange()) {
            $this->statusBeforeSave = $record->status;

            return;
        }

        if ($this->statusBeforeSave !== null && $record->status !== $this->statusBeforeSave) {
            if ($record->status === ContentStatus::InReview) {
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

        $this->statusBeforeSave = $record->status;
    }

    protected function workflowRecordClass(): string
    {
        return Page::class;
    }

    protected function workflowPublishActionName(): string
    {
        return 'publishPage';
    }

    protected function workflowRejectActionName(): string
    {
        return 'rejectPage';
    }

    protected function workflowUpdateActionName(): string
    {
        return 'updatePage';
    }

    protected function workflowPublishedNotificationTitle(): string
    {
        return __('mipress::admin.resources.page.workflow.published_title');
    }

    protected function workflowRejectedNotificationTitle(): string
    {
        return __('mipress::admin.resources.page.workflow.rejected_title');
    }

    protected function workflowScheduledNotificationBody(CarbonInterface $scheduleAt): string
    {
        return __('mipress::admin.resources.page.workflow.scheduled_body', ['date' => $scheduleAt->format('j. n. Y H:i')]);
    }

    protected function workflowReviewNotificationTitle(): string
    {
        return __('mipress::admin.resources.page.workflow.review_request_title');
    }

    protected function workflowReviewNotificationBody(Model $record): string
    {
        if (! $record instanceof Page) {
            return __('mipress::admin.resources.page.workflow.review_fallback_body');
        }

        return __('mipress::admin.resources.page.workflow.review_request_body', ['title' => $record->title]);
    }

    protected function workflowPreviewRouteName(): string
    {
        return 'preview.page';
    }

    protected function workflowPreviewRouteParameterName(): string
    {
        return 'page';
    }

    protected function workflowEditUrl(Model $record): string
    {
        return PageResource::getUrl('edit', ['record' => $record]);
    }

    protected function workflowCompletedRedirectUrl(): string
    {
        return PageResource::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveParentId(array $data): ?int
    {
        $record = $this->getRecord();

        if (! $record instanceof Page) {
            return null;
        }

        return app(HierarchyParentResolver::class)->resolvePageParentForEdit(
            $record,
            data_get($data, 'parent_id'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function shouldPrepareWorkflowData(array $data): bool
    {
        if ($this->workflowIntent !== null) {
            return true;
        }

        $mountedActionName = $this->getMountedAction()?->getName();

        $containsWorkflowFields = array_key_exists('status', $data)
            || array_key_exists('published_at', $data)
            || array_key_exists('scheduled_at', $data);

        if (! $containsWorkflowFields) {
            return false;
        }

        if ($mountedActionName === null) {
            return true;
        }

        return $mountedActionName === $this->workflowUpdateActionName();
    }

    private function shouldProcessStatusChange(): bool
    {
        if ($this->workflowIntent !== null) {
            return true;
        }

        $mountedActionName = $this->getMountedAction()?->getName();

        if ($mountedActionName === null) {
            return true;
        }

        return $mountedActionName === $this->workflowUpdateActionName();
    }
}
