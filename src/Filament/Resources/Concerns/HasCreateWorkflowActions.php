<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Facades\FilamentView;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Services\WorkflowNotificationService;
use MiPress\Core\Services\WorkflowTransitionService;

trait HasCreateWorkflowActions
{
    private string $createIntent = 'draft';

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

    protected function workflowCreateIntent(): string
    {
        return $this->createIntent;
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

    private function workflowTransitions(): WorkflowTransitionService
    {
        return app(WorkflowTransitionService::class);
    }

    private function workflowNotifications(): WorkflowNotificationService
    {
        return app(WorkflowNotificationService::class);
    }
}
