<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation email with a link to set the first password (US-31).
 * Uses the separate "invites" password broker so the link lives longer
 * than a normal password-reset link.
 */
class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token, public string $schoolName) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('invitation.show', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $hours = intdiv((int) config('auth.passwords.invites.expire'), 60);

        return (new MailMessage)
            ->subject(__('You have been invited to :school', ['school' => $this->schoolName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have been given access to the back office of :school.', ['school' => $this->schoolName]))
            ->action(__('Set your password'), $url)
            ->line(__('This link expires in :count hours. Ask an administrator for a new invitation if it has expired.', ['count' => $hours]));
    }
}
