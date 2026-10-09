<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public OrganizationInvitation $invitation,
        public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.url'), '/')
            .'/invitations/'
            .$this->invitation->public_id
            .'?token='
            .urlencode($this->token);

        return (new MailMessage)
            ->subject('Organization invitation: '.$this->invitation->organization->name)
            ->greeting('You have been invited')
            ->line('You have been invited to join '.$this->invitation->organization->name.'.')
            ->line('Assigned role: '.$this->invitation->role.'.')
            ->action('View invitation', $url)
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
