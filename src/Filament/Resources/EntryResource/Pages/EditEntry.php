<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
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
    use HandlesWorkflowValidationErrors;
    use HasContextualCrudNotifications;
    use UsesCurrentPageSubNavigation;

    protected static string $resource = EntryResource::class;

    protected static ?string $navigationLabel = 'Editace';

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected ?EntryStatus $statusBeforeSave = null;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if ($viewOnWebAction = $this->getViewOnWebHeaderAction()) {
            $actions[] = $viewOnWebAction;
        }

        if ($previewAction = $this->getPreviewHeaderAction()) {
            $actions[] = $previewAction;
        }

        $actions[] = $this->getSaveFormAction()
            ->label('Uložit')
            ->icon('far-floppy-disk')
            ->formId('form');

        $actions[] = $this->getCancelFormAction()
            ->label('Zrušit')
            ->icon('far-xmark');

        return $actions;
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

    private function getViewOnWebHeaderAction(): ?Action
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry || auth()->user()?->can('view', $record) !== true) {
            return null;
        }

        if ($record->status !== EntryStatus::Published || blank($record->getPublicUrl())) {
            return null;
        }

        return Action::make('viewLive')
            ->label('Zobrazit na webu')
            ->icon('far-arrow-up-right-from-square')
            ->color('gray')
            ->url($record->getPublicUrl(), shouldOpenInNewTab: true);
    }

    private function getPreviewHeaderAction(): ?Action
    {
        $record = $this->getRecord();

        if (! $record instanceof Entry || auth()->user()?->can('view', $record) !== true) {
            return null;
        }

        return Action::make('preview')
            ->label('Náhled')
            ->icon('far-eye')
            ->color('gray')
            ->url(
                URL::temporarySignedRoute(
                    'preview.entry',
                    now()->addHour(),
                    ['entry' => $record->getKey()],
                ),
                shouldOpenInNewTab: true,
            );
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
