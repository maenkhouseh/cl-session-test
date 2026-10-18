<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitation extends Notification
{
    use Queueable;

    public function __construct(private Workspace $workspace, private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url', config('app.url')), '/').'/invite/'.$this->token;

        return (new MailMessage)
            ->subject("You've been invited to {$this->workspace->name} on ".config('app.name'))
            ->line("You were invited to join the {$this->workspace->name} workspace.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires in '.config('shortie.invite_ttl_days').' days.');
    }
}
