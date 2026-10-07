<?php

namespace App\Domain\Tasks\Messaging;

use Illuminate\Notifications\Notification;

/** Notification channel for SMS and WhatsApp: the notification names the channels wanted and the text, the gateway delivers. */
class MessagingChannel
{
    public function __construct(private readonly MessageGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        foreach ($notification->messageChannels($notifiable) as $channel) {
            $this->gateway->send($channel, $notifiable->phone, $notification->messageText());
        }
    }
}
