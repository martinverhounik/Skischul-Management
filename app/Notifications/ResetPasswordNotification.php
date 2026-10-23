<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Self-service "forgot password" email (US-31). */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $minutes = config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset password'), $url)
            ->line(__('This link expires in :count minutes.', ['count' => $minutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }
}
