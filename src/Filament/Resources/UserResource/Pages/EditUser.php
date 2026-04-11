<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Pages;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\Concerns\HasContextualCrudNotifications;
use MiPress\Core\Filament\Resources\UserResource;

class EditUser extends EditRecord
{
    use HasContextualCrudNotifications;

    protected static string $resource = UserResource::class;

    private ?string $pendingRole = null;

    protected function getHeaderActions(): array
    {
        return [
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
