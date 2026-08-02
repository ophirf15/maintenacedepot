<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DepotAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $typeKey,
        public string $title,
        public ?string $body,
        public ?string $actionUrl,
        public ?string $category,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type_key' => $this->typeKey,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'category' => $this->category,
        ];
    }
}
