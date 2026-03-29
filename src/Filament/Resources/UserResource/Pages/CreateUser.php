<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource;
use MiPress\Core\Notifications\WelcomeNotification;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $role = $data['role'] ?? null;
        $this->pendingRole = $role instanceof UserRole ? $role->value : $role;
        unset($data['role']);

        // Set a random password — user will set their own via password reset
        $data['password'] = Hash::make(Str::random(32));

        return $data;
    }

    protected function beforeCreate(): void
    {
        if ($this->pendingRole === UserRole::SuperAdmin->value) {
            if (User::role(UserRole::SuperAdmin->value)->exists()) {
                Notification::make()
                    ->title('Nelze vytvořit')
                    ->body('Superadministrátor již existuje. Může být pouze jeden.')
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }

    protected function afterCreate(): void
    {
        if ($this->pendingRole !== null) {
            $this->record->syncRoles([$this->pendingRole]);
        }

        // Generate password reset token and send single onboarding email
        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            ['email' => $this->record->email],
            function (CanResetPassword $user, string $token): void {
                $user->notify(new WelcomeNotification(
                    setPasswordUrl: Filament::getResetPasswordUrl($token, $user),
                    verifyEmailUrl: Filament::getVerifyEmailUrl($user),
                ));
            },
        );
    }
}
