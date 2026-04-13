<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\UserResource\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use MiPress\Core\Enums\UserRole;
use MiPress\Core\Filament\Resources\UserResource;
use MiPress\Core\Filament\Tables\Columns\UserColumn;
use MiPress\Core\Notifications\AdminPasswordResetNotification;
use MiPress\Core\Notifications\WelcomeNotification;

class UsersTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['roles']))
            ->columns([
                UserColumn::make('name')
                    ->label('Uživatel')
                    ->state(fn (User $record): User => $record)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (UserRole::tryFrom($state)?->getLabel() ?? $state)
                        : '—'
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        UserRole::SuperAdmin->value => 'danger',
                        UserRole::Admin->value => 'warning',
                        UserRole::Editor->value => 'success',
                        UserRole::Contributor->value => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('email_verified_at')
                    ->label('Ověřen')
                    ->boolean()
                    ->trueIcon('fal-badge-check')
                    ->falseIcon('fal-circle-xmark')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),

                IconColumn::make('has_email_authentication')
                    ->label('MFA')
                    ->boolean()
                    ->trueIcon('fal-shield-check')
                    ->falseIcon('fal-shield-xmark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Vytvořen')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deleted_at')
                    ->label('Smazán')
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(UserRole::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn (Builder $query, string $role): Builder => $query->role($role),
                    )),
                TrashedFilter::make(),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed() && UserResource::canEdit($record)),
                    self::makeSendPasswordResetAction(),
                    self::makeResendInvitationAction(),
                    DeleteAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed() && ! $record->isSuperAdmin()),
                    RestoreAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && $record->trashed()),
                    ForceDeleteAction::make()
                        ->visible(fn (User $record): bool => self::canManageUsers() && $record->trashed() && ! $record->isSuperAdmin()),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                    RestoreBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                    ForceDeleteBulkAction::make()
                        ->visible(fn (): bool => self::canManageUsers()),
                ]),
            ]);
    }

    private static function makeSendPasswordResetAction(): Action
    {
        return Action::make('sendPasswordReset')
            ->label('Odeslat reset hesla')
            ->icon('far-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => 'Odeslat reset hesla uživateli "'.$record->name.'"?')
            ->modalDescription('Uživateli bude na e-mail odeslán odkaz pro nastavení nového hesla.')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed())
            ->action(function (User $record): void {
                $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                    ['email' => $record->email],
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
            });
    }

    private static function makeResendInvitationAction(): Action
    {
        return Action::make('resendInvitation')
            ->label('Znovu poslat pozvánku')
            ->icon('far-envelope')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => 'Znovu poslat pozvánku uživateli "'.$record->name.'"?')
            ->modalDescription('Uživateli bude znovu odeslán uvítací e-mail s odkazy pro ověření e-mailu a nastavení hesla.')
            ->modalSubmitActionLabel('Odeslat')
            ->visible(fn (User $record): bool => self::canManageUsers() && ! $record->trashed() && $record->email_verified_at === null)
            ->action(function (User $record): void {
                $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                    ['email' => $record->email],
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
            });
    }

    private static function canManageUsers(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
