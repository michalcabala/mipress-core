<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
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
        $this->pendingRole = $data['role'] ?? null;
        unset($data['role']);
        unset($data['password_confirmation']);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        // Prevent degrading SuperAdmin role
        if ($this->record->isSuperAdmin() && $this->pendingRole !== UserRole::SuperAdmin->value) {
            Notification::make()
                ->title('Chyba zabezpečení')
                ->body('Roli Superadministrátora nelze změnit.')
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
                    ->title('Nelze přiřadit')
                    ->body('Superadministrátor již existuje. Může být pouze jeden.')
                    ->danger()
                    ->send();

                $this->halt();
            }

            if (! $this->record->email_verified_at) {
                Notification::make()
                    ->title('Nelze přiřadit')
                    ->body('Superadministrátor musí mít ověřený e-mail.')
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
