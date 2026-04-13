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
                ->label('Odeslat reset hesla')
                ->icon('far-key')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Odeslat reset hesla uživateli "'.$this->record->name.'"?')
                ->modalDescription('Uživateli bude na e-mail odeslán odkaz pro nastavení nového hesla.')
                ->modalSubmitActionLabel('Odeslat')
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
                            ->title('E-mail pro reset hesla odeslán')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('E-mail se nepodařilo odeslat')
                        ->body(trans($status))
                        ->danger()
                        ->send();
                }),

            Action::make('resendInvitation')
                ->label('Znovu poslat pozvánku')
                ->icon('far-envelope')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => 'Znovu poslat pozvánku uživateli "'.$this->record->name.'"?')
                ->modalDescription('Uživateli bude znovu odeslán uvítací e-mail s odkazy pro ověření e-mailu a nastavení hesla.')
                ->modalSubmitActionLabel('Odeslat')
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
                            ->title('Pozvánka byla znovu odeslána')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('E-mail se nepodařilo odeslat')
                        ->body(trans($status))
                        ->danger()
                        ->send();
                }),

            DeleteAction::make()
                ->modalHeading(fn (): string => 'Smazat uživatele "'.$this->record->name.'"?')
                ->modalDescription('Účet uživatele "'.$this->record->name.'" bude přesunut do koše a půjde obnovit, pokud není trvale smazán.')
                ->successNotificationTitle('Uživatel byl smazán')
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
                ->title('Roli superadministrátora nelze změnit')
                ->body('Uživatel "'.$this->record->name.'" musí zůstat superadministrátorem.')
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
                    ->title('Roli superadministrátora nelze přiřadit')
                    ->body('Uživateli "'.$this->record->name.'" nelze roli přiřadit, protože jiný superadministrátor už existuje.')
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
