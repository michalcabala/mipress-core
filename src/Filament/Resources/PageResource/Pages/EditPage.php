<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class EditPage extends EditRecord
{
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }
    use UsesCurrentPageSubNavigation;

    protected static string $resource = PageResource::class;

    protected static ?string $navigationLabel = 'Editace';

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected ?EntryStatus $statusBeforeSave = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Aktualizovat')
                ->icon('far-floppy-disk')
                ->formId('form'),
            $this->getCancelFormAction()
                ->icon('far-xmark'),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
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
            && in_array($record->status, [EntryStatus::Published, EntryStatus::InReview, EntryStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        $data['parent_id'] = $this->resolveParentId($data);

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

        if ($this->statusBeforeSave !== null && $record->status !== $this->statusBeforeSave) {
            AuditLog::logStatusChange(
                $record,
                $record->status,
                $this->statusBeforeSave,
                $record->status === EntryStatus::Rejected ? $record->review_note : null,
            );

            if ($record->status === EntryStatus::InReview) {
                app(WorkflowNotificationService::class)->sendReviewRequestedDatabaseNotifications(
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

        $this->statusBeforeSave = $record->status;
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
}
