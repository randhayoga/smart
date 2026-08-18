<?php

namespace App\Notifications;

use App\Notifications\Channels\MercureChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    public string $title;
    public string $message;
    public string $type;
    public ?string $url;
    public array $extra;

    /**
     * Create a new notification instance.
     *
     * @param string $title
     * @param string $message
     * @param string $type 'info' | 'success' | 'warning' | 'error'
     * @param string|null $url
     * @param array $extra
     */
    public function __construct(
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        array $extra = []
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->url = $url;
        $this->extra = $extra;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string|class-string>
     */
    public function via(object $notifiable): array
    {
        return ['database', MercureChannel::class];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $this->url,
            'extra' => $this->extra,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the Mercure real-time broadcast representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toMercure(object $notifiable): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'url' => $this->url,
            'read' => false,
            'extra' => $this->extra,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

