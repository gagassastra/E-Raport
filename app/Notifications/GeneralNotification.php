<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class GeneralNotification extends Notification
{
    public function __construct(public string $pesan, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['pesan' => $this->pesan, 'url' => $this->url];
    }
}
