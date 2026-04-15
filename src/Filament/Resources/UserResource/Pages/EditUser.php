<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Password;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\UserResource;
use MiPress\Core\Notifications\AdminPasswordResetNotification;
use MiPress\Core\Notifications\WelcomeNotification;

class EditUser extends EditRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendPasswordReset')
                ->label(__('mipress::admin.resources.user.actions.send_password_reset.label'))
                ->icon('far-key')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => __('mipress::admin.resources.user.actions.send_password_reset.modal_heading', ['name' => $this->record->name]))
                ->modalDescription(__('mipress::admin.resources.user.actions.send_password_reset.modal_description'))
                ->modalSubmitActionLabel(__('mipress::admin.resources.user.actions.send_password_reset.modal_submit'))
                ->visible(fn (): bool => ! $this->record->trashed())
                ->action(function (): void {
                    $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                        ['email' => $this->record->email],
                        function (User $user, string $token): void {
                            $user->notify(new AdminPasswordResetNotification(
                                resetUrl: Filament::getResetPasswordUrl($token, $user),
                            ));
                        },
                    );

                    if ($status === Password::RESET_LINK_SENT) {
                        Notification::make()
                            ->title(__('mipress::admin.resources.user.notifications.password_reset_sent'))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('mipress::admin.resources.user.notifications.email_send_failed'))
                        ->body(trans($status))
                        ->danger()
                        ->send();
                }),

            Action::make('resendInvitation')
                ->label(__('mipress::admin.resources.user.actions.resend_invitation.label'))
                ->icon('far-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => __('mipress::admin.resources.user.actions.resend_invitation.modal_heading', ['name' => $this->record->name]))
                ->modalDescription(__('mipress::admin.resources.user.actions.resend_invitation.modal_description'))
                ->modalSubmitActionLabel(__('mipress::admin.resources.user.actions.resend_invitation.modal_submit'))
                ->visible(fn (): bool => ! $this->record->trashed() && $this->record->email_verified_at === null)
                ->action(function (): void {
                    $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                        ['email' => $this->record->email],
                        function (User $user, string $token): void {
                            $user->notify(new WelcomeNotification(
                                setPasswordUrl: Filament::getResetPasswordUrl($token, $user),
                                verifyEmailUrl: Filament::getVerifyEmailUrl($user),
                            ));
                        },
                    );

                    if ($status === Password::RESET_LINK_SENT) {
                        Notification::make()
                            ->title(__('mipress::admin.resources.user.notifications.invitation_resent'))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('mipress::admin.resources.user.notifications.email_send_failed'))
                        ->body(trans($status))
                        ->danger()
                        ->send();
                }),

            DeleteAction::make()
                ->modalHeading(fn (): string => __('mipress::admin.resources.user.actions.delete.modal_heading', ['name' => $this->record->name]))
                ->modalDescription(fn (): string => __('mipress::admin.resources.user.actions.delete.modal_description', ['name' => $this->record->name]))
                ->successNotificationTitle(__('mipress::admin.resources.user.actions.delete.success_title'))
                ->hidden(fn (): bool => $this->record->isSuperAdmin()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->first()?->name;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $role = $data['role'] ?? null;
        $this->pendingRole = $role instanceof UserRole ? $role->value : $role;
        unset($data['role']);

        return $data;
    }

    protected function beforeSave(): void
    {
        // Prevent degrading SuperAdmin role
        if ($this->record->isSuperAdmin() && $this->pendingRole !== UserRole::SuperAdmin->value) {
            Notification::make()
                ->title(__('mipress::admin.resources.user.notifications.change_super_admin_role_forbidden.title'))
                ->body(__('mipress::admin.resources.user.notifications.change_super_admin_role_forbidden.body', ['name' => $this->record->name]))
                ->danger()
                ->send();

            $this->halt();
        }

        // Enforce requirements when assigning SuperAdmin role
        if ($this->pendingRole === UserRole::SuperAdmin->value) {
            $conflictExists = User::role(UserRole::SuperAdmin->value)
                ->where('id', '!=', $this->record->id)
                ->exists();

            if ($conflictExists) {
                Notification::make()
                    ->title(__('mipress::admin.resources.user.notifications.assign_super_admin_role_forbidden.title'))
                    ->body(__('mipress::admin.resources.user.notifications.assign_super_admin_role_forbidden.body', ['name' => $this->record->name]))
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function afterSave(): void
    {
        if ($this->pendingRole !== null) {
            $this->record->syncRoles([$this->pendingRole]);
        }
    }
}
