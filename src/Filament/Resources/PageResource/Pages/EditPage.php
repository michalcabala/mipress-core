<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;
use MiPress\Core\Services\HierarchyParentResolver;
use MiPress\Core\Services\WorkflowTransitionService;

class EditPage extends EditRecord
{
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, HasWorkflowActions, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }

    protected static string $resource = PageResource::class;

    protected static ?string $navigationLabel = 'Editace';

    protected static string|\BackedEnum|null $navigationIcon = 'far-pen-to-square';

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Zrušit')
            ->color('gray')
            ->url($this->getRedirectUrl());
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

        if (
            $record instanceof Page
            && $user?->hasRole('contributor')
            && (int) $record->author_id === (int) $user->getKey()
            && in_array($record->status, [EntryStatus::Published, EntryStatus::InReview, EntryStatus::Scheduled], true)
        ) {
            $data['slug'] = $record->slug;
        }

        $data['parent_id'] = $this->resolveParentId($data);

        if ($this->workflowIntent === 'review') {
            $data = app(WorkflowTransitionService::class)->prepareReviewData($data);
        }

        return $data;
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
        return 'Stránka publikována';
    }

    protected function workflowRejectedNotificationTitle(): string
    {
        return 'Stránka zamítnuta';
    }

    protected function workflowScheduledNotificationBody(CarbonInterface $scheduleAt): string
    {
        return 'Stránka bude automaticky publikována '.$scheduleAt->format('j. n. Y H:i').'.';
    }

    protected function workflowReviewNotificationTitle(): string
    {
        return 'Nová stránka ke schválení';
    }

    protected function workflowReviewNotificationBody(Model $record): string
    {
        if (! $record instanceof Page) {
            return 'Stránka čeká na schválení publikace.';
        }

        return 'Stránka "'.$record->title.'" čeká na schválení publikace.';
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
        if (! $record instanceof Page) {
            return PageResource::getUrl('index');
        }

        return PageResource::getUrl('edit', ['record' => $record]);
    }
}
