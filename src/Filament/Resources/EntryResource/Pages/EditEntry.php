<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\UsesCurrentPageSubNavigation;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;
use MiPress\Core\Services\EntryTaxonomySyncService;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

class EditEntry extends EditRecord
{
    use HasContextualCrudNotifications;
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }
    use UsesCurrentPageSubNavigation;

    protected static string $resource = EntryResource::class;

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
        $collection = $this->getRecord()->collection;

        return static::$resource::getUrl('index', [
            'collection' => $collection?->handle,
        ]);
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

        $breadcrumbs = [
            static::getResource()::getUrl('index', ['collection' => $collection->handle]) => $collection->name,
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
            && in_array($record->status, [EntryStatus::Published, EntryStatus::InReview, EntryStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        $data['parent_id'] = $this->resolveParentId($data);

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
                    title: 'Nový obsah ke schválení',
                    body: 'Položka "'.$record->title.'" čeká na schválení publikace.',
                    editUrl: EntryResource::getUrl('edit', [
                        'record' => $record,
                        'collection' => $record->collection?->handle,
                    ]),
                    previewRouteName: 'preview.entry',
                    previewRouteParameterName: 'entry',
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

        if (! $record instanceof Entry || ! $record->collection?->hierarchical) {
            return null;
        }

        return app(HierarchyParentResolver::class)->resolveEntryParentForEdit(
            $record,
            data_get($data, 'parent_id'),
        );
    }
}
