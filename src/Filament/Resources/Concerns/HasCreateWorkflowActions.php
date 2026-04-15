<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Facades\FilamentView;
use MiPress\Core\Enums\ContentStatus;
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
                ->label(__('mipress::admin.create_workflow.more_actions'))
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
                ->label(__('mipress::admin.create_workflow.more_actions'))
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
            ->label(__('mipress::admin.create_workflow.save_draft'))
            ->icon(ContentStatus::Draft->getIcon())
            ->color(ContentStatus::Draft->getColor())
            ->submit('createAsDraft')
            ->formId('form');
    }

    private function makeCreateReviewAction(): Action
    {
        return Action::make('createReview')
            ->label(__('mipress::admin.create_workflow.submit_for_review'))
            ->icon(ContentStatus::InReview->getIcon())
            ->color(ContentStatus::InReview->getColor())
            ->submit('createAndSubmitForReview')
            ->formId('form');
    }

    private function makeCreatePublishAction(): Action
    {
        return Action::make('createPublish')
            ->label(__('mipress::admin.create_workflow.publish'))
            ->icon(ContentStatus::Published->getIcon())
            ->color(ContentStatus::Published->getColor())
            ->submit('createAndPublish')
            ->formId('form');
    }

    private function makeCancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('mipress::admin.create_workflow.cancel'))
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
