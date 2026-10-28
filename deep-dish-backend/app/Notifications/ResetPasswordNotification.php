<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $token,
        private string $tipo,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $email = urlencode($notifiable->getEmailForPasswordReset());
        $url = "{$frontendUrl}/reset-password?token={$this->token}&email={$email}&tipo={$this->tipo}";

        return (new MailMessage)
            ->subject('Recuperação de senha — Deep Dish')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em **60 minutos**.')
            ->line('Se você não solicitou a recuperação de senha, ignore este e-mail — sua senha permanece a mesma.');
    }
}
