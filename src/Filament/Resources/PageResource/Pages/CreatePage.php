<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\PageResource\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\PageResource;
use MiPress\Core\Models\Blueprint;
use MiPress\Core\Models\Page;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    private string $createIntent = 'draft';

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $blueprint = Blueprint::where('handle', 'page')->first();

        if ($blueprint) {
            $data['blueprint_id'] = $blueprint->id;
        }

        $data['parent_id'] = $this->resolveParentId($data);
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

    private function resolveParentId(array $data): ?int
    {
        $parentId = data_get($data, 'parent_id');

        if (! is_numeric($parentId)) {
            return null;
        }

        return Page::query()
            ->whereKey((int) $parentId)
            ->value('id');
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [
                $this->makeCreateDraftAction(),
                $this->makeCancelAction(),
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
            $actions[] = $this->makeCancelAction();

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
            $actions[] = $this->makeCancelAction();

            return $actions;
        }

        return [
            $this->makeCreateDraftAction(),
            $this->makeCancelAction(),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'Nová stránka';
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

        if (! $record instanceof Page || $record->status !== EntryStatus::InReview) {
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
            ->title('Nová stránka ke schválení')
            ->body('Stránka "'.$record->title.'" čeká na schválení publikace.')
            ->warning()
            ->actions([
                Action::make('approve')
                    ->label('Schválit')
                    ->button()
                    ->color('success')
                    ->url(
                        PageResource::getUrl('edit', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
                Action::make('view')
                    ->label('Zobrazit')
                    ->button()
                    ->color('gray')
                    ->url(
                        URL::temporarySignedRoute('preview.page', now()->addHour(), ['page' => $record->getKey()]),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
                Action::make('edit')
                    ->label('Upravit')
                    ->button()
                    ->color('primary')
                    ->url(
                        PageResource::getUrl('edit', ['record' => $record]),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
            ])
            ->sendToDatabase($approvers);
    }

    private function makeCreateDraftAction(): Action
    {
        return Action::make('createDraft')
            ->label('Uložit koncept')
            ->icon(EntryStatus::Draft->getIcon())
            ->color(EntryStatus::Draft->getColor())
            ->submit('createAsDraft')
            ->formId('form');
    }

    private function makeCreateReviewAction(): Action
    {
        return Action::make('createReview')
            ->label('Odeslat ke schválení')
            ->icon(EntryStatus::InReview->getIcon())
            ->color(EntryStatus::InReview->getColor())
            ->submit('createAndSubmitForReview')
            ->formId('form');
    }

    private function makeCreatePublishAction(): Action
    {
        return Action::make('createPublish')
            ->label('Publikovat')
            ->icon(EntryStatus::Published->getIcon())
            ->color(EntryStatus::Published->getColor())
            ->submit('createAndPublish')
            ->formId('form');
    }

    private function makeCancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Zrušit')
            ->icon('far-xmark')
            ->color('gray')
            ->action(function (): void {
                $redirectUrl = $this->getRedirectUrl();

                $this->redirect($redirectUrl, navigate: FilamentView::hasSpaMode($redirectUrl));
            });
    }
}
