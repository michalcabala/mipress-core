<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingRole = $data['role'] ?? null;
        unset($data['role']);
        unset($data['password_confirmation']);

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
    }
}
