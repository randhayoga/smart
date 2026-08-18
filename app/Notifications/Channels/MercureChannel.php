<?php

namespace App\Notifications\Channels;

use App\Services\Mercure\MercurePublisher;
use Illuminate\Notifications\Notification;

class MercureChannel
{
    public function __construct(
        protected MercurePublisher $publisher
    ) {}

    /**
     * Send the given notification via Mercure.
     *
     * @param object $notifiable
     * @param Notification $notification
     * @return void
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toMercure')) {
            return;
        }

        $data = $notification->toMercure($notifiable);

        if (is_array($data)) {
            $this->publisher->publishToUser($notifiable, $data);
        }
    }
}
