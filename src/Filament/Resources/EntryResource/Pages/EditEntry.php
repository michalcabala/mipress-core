<?php

declare(strict_types=1);

namespace MiPress\Core\Filament\Resources\EntryResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use MiPress\Core\Enums\EntryStatus;
use MiPress\Core\Filament\Resources\EntryResource;
use MiPress\Core\Models\AuditLog;
use MiPress\Core\Models\Entry;

class EditEntry extends EditRecord
{
    protected static string $resource = EntryResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->formId('form'),
            Action::make('submitForReview')
                ->label('Odeslat ke schválení')
                ->icon('fas-paper-plane')
                ->color('info')
                ->visible(fn (Entry $record): bool => in_array($record->status, [EntryStatus::Draft, EntryStatus::Rejected])
                    && ! auth()->user()?->can('entry.publish'))
                ->requiresConfirmation()
                ->modalHeading('Odeslat ke schválení')
                ->modalDescription('Položka bude odeslána ke schválení. Po schválení bude publikována.')
                ->action(function (Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::InReview;
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::InReview, $oldStatus);
                    Notification::make()->title('Odesláno ke schválení')->success()->send();
                }),

            Action::make('publishDirect')
                ->label('Publikovat')
                ->icon('fas-circle-check')
                ->color('success')
                ->visible(fn (Entry $record): bool => $record->status === EntryStatus::Draft
                    && auth()->user()?->can('entry.publish'))
                ->requiresConfirmation()
                ->modalHeading('Publikovat položku')
                ->action(function (Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::Published;
                    $record->published_at ??= now();
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);
                    Notification::make()->title('Položka publikována')->success()->send();
                }),

            Action::make('approve')
                ->label('Schválit a publikovat')
                ->icon('fas-circle-check')
                ->color('success')
                ->visible(fn (Entry $record): bool => $record->status === EntryStatus::InReview
                    && auth()->user()?->can('entry.publish'))
                ->requiresConfirmation()
                ->modalHeading('Schválit a publikovat')
                ->action(function (Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::Published;
                    $record->review_note = null;
                    $record->published_at ??= now();
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::Published, $oldStatus);
                    Notification::make()->title('Položka schválena a publikována')->success()->send();
                }),

            Action::make('schedule')
                ->label('Naplánovat')
                ->icon('fas-calendar-check')
                ->color('warning')
                ->visible(fn (Entry $record): bool => $record->status === EntryStatus::InReview
                    && auth()->user()?->can('entry.publish'))
                ->schema([
                    DateTimePicker::make('scheduled_at')
                        ->label('Datum publikování')
                        ->required()
                        ->minDate(now()),
                ])
                ->action(function (array $data, Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::Scheduled;
                    $record->published_at = $data['scheduled_at'];
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::Scheduled, $oldStatus);
                    Notification::make()->title('Položka naplánována')->success()->send();
                }),

            Action::make('reject')
                ->label('Zamítnout')
                ->icon('fas-circle-xmark')
                ->color('danger')
                ->visible(fn (Entry $record): bool => $record->status === EntryStatus::InReview
                    && auth()->user()?->can('entry.publish'))
                ->schema([
                    Textarea::make('reason')
                        ->label('Důvod zamítnutí')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data, Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::Rejected;
                    $record->review_note = $data['reason'];
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::Rejected, $oldStatus, $data['reason']);
                    Notification::make()->title('Položka zamítnuta')->warning()->send();
                }),

            Action::make('returnToDraft')
                ->label('Vrátit do konceptu')
                ->icon('fas-rotate-left')
                ->color('gray')
                ->visible(fn (Entry $record): bool => $record->status === EntryStatus::Published
                    && (auth()->id() === $record->author_id || auth()->user()?->can('entry.publish')))
                ->requiresConfirmation()
                ->modalHeading('Vrátit do konceptu')
                ->action(function (Entry $record): void {
                    $oldStatus = $record->status;
                    $record->status = EntryStatus::Draft;
                    $record->save();
                    AuditLog::logStatusChange($record, EntryStatus::Draft, $oldStatus);
                    Notification::make()->title('Položka vrácena do konceptu')->info()->send();
                }),

            RestoreAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $collection = $this->getRecord()->collection;

        return static::$resource::getUrl('index', [
            'collection' => $collection?->handle,
        ]);
    }
}
