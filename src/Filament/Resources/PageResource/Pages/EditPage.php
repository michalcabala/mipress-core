<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use Blendbyte\FilamentResourceLock\Resources\Pages\Concerns\UsesResourceLock;
use Carbon\CarbonInterface;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\Concerns\HandlesResourceLockRenewal;
use MiPress\Core\Filament\Resources\Concerns\HandlesWorkflowValidationErrors;
use MiPress\Core\Filament\Resources\Concerns\HasWorkflowActions;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Page;

class EditPage extends EditRecord
{
    use HandlesResourceLockRenewal, HandlesWorkflowValidationErrors, HasWorkflowActions, UsesResourceLock {
        HandlesResourceLockRenewal::renewLock insteadof UsesResourceLock;
    }

    protected static string $resource = PageResource::class;

    protected Width|string|null $maxWidth = Width::Full;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

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
            $data['status'] = EntryStatus::InReview;
            $data['review_note'] = null;
        }

        return $data;
    }

    private function resolveParentId(array $data): ?int
    {
        $record = $this->getRecord();

        if (! $record instanceof Page) {
            return null;
        }

        $parentId = data_get($data, 'parent_id');

        if (! is_numeric($parentId)) {
            return null;
        }

        $resolvedParentId = Page::query()
            ->whereKey((int) $parentId)
            ->value('id');

        if (! is_numeric($resolvedParentId)) {
            return null;
        }

        $resolvedParentId = (int) $resolvedParentId;

        if ($resolvedParentId === (int) $record->getKey()) {
            return null;
        }

        if ($this->wouldCreateHierarchyCycle($record, $resolvedParentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Nelze vybrat podřízenou stránku jako nadřazenou.',
            ]);
        }

        return $resolvedParentId;
    }

    private function wouldCreateHierarchyCycle(Page $record, int $candidateParentId): bool
    {
        $currentId = $candidateParentId;
        $visited = [];

        while ($currentId > 0) {
            if ($currentId === (int) $record->getKey()) {
                return true;
            }

            if (isset($visited[$currentId])) {
                return true;
            }

            $visited[$currentId] = true;

            $parentId = Page::query()
                ->whereKey($currentId)
                ->value('parent_id');

            if (! is_numeric($parentId)) {
                return false;
            }

            $currentId = (int) $parentId;
        }

        return false;
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
