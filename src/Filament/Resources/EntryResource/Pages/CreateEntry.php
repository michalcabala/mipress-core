<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\Collection;
use MiPress\Core\Models\Entry;

class CreateEntry extends CreateRecord
{
    protected static string $resource = EntryResource::class;

    public string $collectionHandle = '';

    private string $createIntent = 'draft';

    public function mount(?string $collection = null): void
    {
        if (blank($this->collectionHandle)) {
            $this->collectionHandle = $collection ?: (string) request()->query('collection', '');
        }

        parent::mount();
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index', [
            'collection' => $this->collectionHandle,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $collection = filled($this->collectionHandle)
            ? Collection::where('handle', $this->collectionHandle)->first()
            : null;

        if ($collection && empty($data['collection_id'])) {
            $data['collection_id'] = $collection->id;
        }

        if ($collection?->blueprint_id) {
            $data['blueprint_id'] = $collection->blueprint_id;
        }

        $data['parent_id'] = $this->resolveParentId($data, $collection);

        $data['review_note'] = null;

        if ($this->createIntent === 'review') {
            $data['status'] = EntryStatus::InReview;

            return $data;
        }

        if ($this->createIntent === 'publish') {
            $publishedAt = data_get($data, 'published_at');

            if ($publishedAt && now()->lt($publishedAt)) {
                $data['status'] = EntryStatus::Scheduled;

                return $data;
            }

            $data['status'] = EntryStatus::Published;
            $data['published_at'] = $publishedAt ?: now();

            return $data;
        }

        $data['status'] = EntryStatus::Draft;

        return $data;
    }

    private function resolveParentId(array $data, ?Collection $collection): ?int
    {
        $parentId = data_get($data, 'parent_id');

        if (! $collection?->hierarchical || ! is_numeric($parentId)) {
            return null;
        }

        return Entry::query()
            ->where('collection_id', $collection->id)
            ->whereKey((int) $parentId)
            ->value('id');
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [
                $this->makeCreateDraftAction(),
            ];
        }

        $actions = [];

        if ($user->hasRole('contributor')) {
            $actions[] = $this->makeCreateReviewAction();
            $actions[] = ActionGroup::make([
                $this->makeCreateDraftAction(),
            ])
                ->label('Další akce')
                ->icon('far-ellipsis')
                ->color('gray')
                ->button();

            return $actions;
        }

        if ($user->hasPermissionTo('entry.publish')) {
            $actions[] = $this->makeCreatePublishAction();
            $actions[] = ActionGroup::make([
                $this->makeCreateDraftAction(),
            ])
                ->label('Další akce')
                ->icon('far-ellipsis')
                ->color('gray')
                ->button();

            return $actions;
        }

        return [
            $this->makeCreateDraftAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        $collection = filled($this->collectionHandle)
            ? Collection::where('handle', $this->collectionHandle)->first()
            : null;

        return $collection
            ? 'Nová položka — '.$collection->name
            : 'Nová položka';
    }

    public function createAsDraft(): void
    {
        $this->createIntent = 'draft';

        $this->create();
    }

    public function createAndSubmitForReview(): void
    {
        $this->createIntent = 'review';

        $this->create();
    }

    public function createAndPublish(): void
    {
        $this->createIntent = 'publish';

        $this->create();
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof Entry || $record->status !== EntryStatus::InReview) {
            return;
        }

        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approvers = User::query()
            ->permission('entry.publish')
            ->whereKeyNot(auth()->id())
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Nový obsah ke schválení')
            ->body('Položka "'.$record->title.'" čeká na schválení publikace.')
            ->warning()
            ->sendToDatabase($approvers);
    }

    private function makeCreateDraftAction(): Action
    {
        return Action::make('createDraft')
            ->label('Uložit koncept')
            ->icon('far-floppy-disk')
            ->color('gray')
            ->submit('createAsDraft')
            ->formId('form');
    }

    private function makeCreateReviewAction(): Action
    {
        return Action::make('createReview')
            ->label('Odeslat ke schválení')
            ->icon('far-paper-plane')
            ->color('primary')
            ->submit('createAndSubmitForReview')
            ->formId('form');
    }

    private function makeCreatePublishAction(): Action
    {
        return Action::make('createPublish')
            ->label('Publikovat')
            ->icon('far-circle-check')
            ->color('primary')
            ->submit('createAndPublish')
            ->formId('form');
    }
}
