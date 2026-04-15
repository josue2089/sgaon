<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage())
            ->subject('ON English - Recuperacion de contrasena')
            ->greeting('Hola '.($notifiable->name ?? ''))
            ->line('Recibimos una solicitud para restablecer la contrasena de tu cuenta en ON English.')
            ->action('Restablecer contrasena', $url)
            ->line('Este enlace vence en '.$minutes.' minutos.')
            ->line('Si no solicitaste este cambio, puedes ignorar este mensaje.');
    }
}
