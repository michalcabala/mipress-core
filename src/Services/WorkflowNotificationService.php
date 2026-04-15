<?php

declare(strict_types=1);

namespace MiPress\Core\Services;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class WorkflowNotificationService
{
    public function sendReviewRequestedDatabaseNotifications(
        Model $record,
        string $permission,
        string $title,
        string $body,
        string $editUrl,
        string $previewRouteName,
        string $previewRouteParameterName,
    ): void {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $approvers = User::query()
            ->permission($permission)
            ->whereKeyNot(auth()->id())
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->actions([
                Action::make('approve')
                    ->label(__('mipress::admin.workflow_notifications.actions.approve'))
                    ->button()
                    ->color('success')
                    ->url($editUrl, shouldOpenInNewTab: true)
                    ->markAsRead(),
                Action::make('view')
                    ->label(__('mipress::admin.workflow_notifications.actions.view'))
                    ->button()
                    ->color('gray')
                    ->url(
                        URL::temporarySignedRoute(
                            $previewRouteName,
                            now()->addHour(),
                            [$previewRouteParameterName => $record->getKey()],
                        ),
                        shouldOpenInNewTab: true,
                    )
                    ->markAsRead(),
                Action::make('edit')
                    ->label(__('mipress::admin.workflow_notifications.actions.edit'))
                    ->button()
                    ->color('primary')
                    ->url($editUrl, shouldOpenInNewTab: true)
                    ->markAsRead(),
            ])
            ->sendToDatabase($approvers);
    }
}
