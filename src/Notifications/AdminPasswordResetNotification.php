<?php

declare(strict_types=1);

namespace MiPress\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $resetUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Nastavení nového hesla')
            ->greeting('Dobrý den, '.$notifiable->name.'!')
            ->line('Administrátor vám odeslal odkaz pro nastavení nového hesla k vašemu účtu v administraci webu.')
            ->action('Nastavit nové heslo', $this->resetUrl)
            ->line('Odkaz vyprší za '.$expireMinutes.' minut.')
            ->line('Pokud jste o změnu hesla nežádali, kontaktujte administrátora.')
            ->salutation('S pozdravem, '.config('app.name'));
    }
}
