<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $tipo) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = URL::temporarySignedRoute(
            'email.verify',
            now()->addHours(24),
            [
                'tipo' => $this->tipo,
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        // Fora de produção o link também vai para storage/logs/laravel.log: assim
        // dá para verificar qualquer conta de teste sem abrir a caixa de e-mail.
        // Nunca em produção — é um link de autenticação válido por 24 horas.
        if (! app()->isProduction()) {
            Log::info('Link de verificação de e-mail (apenas fora de produção)', [
                'email' => $notifiable->getEmailForVerification(),
                'url' => $verificationUrl,
            ]);
        }

        return (new MailMessage)
            ->subject('Verifique seu e-mail — Deep Dish')
            ->greeting('Olá, '.$notifiable->name.'!')
            ->line('Clique no botão abaixo para confirmar seu endereço de e-mail.')
            ->action('Verificar e-mail', $verificationUrl)
            ->line('Este link expira em **24 horas**.')
            ->line('Se você não criou uma conta no Deep Dish, ignore este e-mail.');
    }
}
