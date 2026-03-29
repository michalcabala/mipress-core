<?php

declare(strict_types=1);

namespace MiPress\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $setPasswordUrl,
        public readonly string $verifyEmailUrl,
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
        return (new MailMessage)
            ->subject('Vítejte v administraci — nastavte si přístup')
            ->greeting('Dobrý den, ' . $notifiable->name . '!')
            ->line('Byl vám vytvořen účet v administraci webu. Pro dokončení registrace proveďte prosím dva kroky:')
            ->line('**1. Ověřte svůj e-mail** kliknutím na tlačítko níže:')
            ->action('Ověřit e-mail', $this->verifyEmailUrl)
            ->line('**2. Nastavte si heslo** pomocí tohoto odkazu:')
            ->action('Nastavit heslo', $this->setPasswordUrl)
            ->line('Oba odkazy vyprší za ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') . ' minut.')
            ->line('Pokud jste o vytvoření účtu nežádali, kontaktujte administrátora.')
            ->salutation('S pozdravem, ' . config('app.name'));
    }
}
