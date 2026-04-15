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
                    ->label(__('mipress::admin.resources.user.table.columns.user'))
                    ->state(fn (User $record): User => $record)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('mipress::admin.resources.user.table.columns.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('mipress::admin.resources.user.table.columns.role'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (UserRole::tryFrom($state)?->getLabel() ?? $state)
                        : __('mipress::admin.common.empty')
                    )
                    ->color(fn (?string $state): string => match ($state) {
                        UserRole::SuperAdmin->value => 'danger',
                        UserRole::Admin->value => 'warning',
                        UserRole::Editor->value => 'success',
                        UserRole::Contributor->value => 'info',
                        default => 'gray',
                    }),

                IconColumn::make('email_verified_at')
                    ->label(__('mipress::admin.resources.user.table.columns.verified'))
                    ->boolean()
                    ->trueIcon('fal-badge-check')
                    ->falseIcon('fal-circle-xmark')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null),

                IconColumn::make('has_email_authentication')
                    ->label(__('mipress::admin.resources.user.table.columns.mfa'))
                    ->boolean()
                    ->trueIcon('fal-shield-check')
                    ->falseIcon('fal-shield-xmark')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label(__('mipress::admin.resources.user.table.columns.created_at'))
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('deleted_at')
                    ->label(__('mipress::admin.resources.user.table.columns.deleted_at'))
                    ->isoDateTime('LLL')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->label(__('mipress::admin.resources.user.table.columns.role'))
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
            ->label(__('mipress::admin.resources.user.actions.send_password_reset.label'))
            ->icon('far-key')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => __('mipress::admin.resources.user.actions.send_password_reset.modal_heading', ['name' => $record->name]))
            ->modalDescription(__('mipress::admin.resources.user.actions.send_password_reset.modal_description'))
            ->modalSubmitActionLabel(__('mipress::admin.resources.user.actions.send_password_reset.modal_submit'))
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
            });
    }

    private static function makeResendInvitationAction(): Action
    {
        return Action::make('resendInvitation')
            ->label(__('mipress::admin.resources.user.actions.resend_invitation.label'))
            ->icon('far-envelope')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => __('mipress::admin.resources.user.actions.resend_invitation.modal_heading', ['name' => $record->name]))
            ->modalDescription(__('mipress::admin.resources.user.actions.resend_invitation.modal_description'))
            ->modalSubmitActionLabel(__('mipress::admin.resources.user.actions.resend_invitation.modal_submit'))
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
