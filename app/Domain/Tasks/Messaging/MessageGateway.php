<?php

namespace App\Domain\Tasks\Messaging;

/** Sends a short text to a phone number. Implement this for the SMS or WhatsApp provider the farm uses and select it with MESSAGING_DRIVER. */
interface MessageGateway
{
    /** @param 'sms'|'whatsapp' $channel */
    public function send(string $channel, string $to, string $body): void;
}
