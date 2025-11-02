<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    use Queueable;

    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        $actionUrl = config('app.url') . '/api/user/reset-password/' . $this->token;

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->view('vendor.notifications.email', [
                'user' => $notifiable,
                'actionUrl' => $actionUrl,
            ]);
    }
}
