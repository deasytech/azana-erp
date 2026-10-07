<?php

namespace App\Domain\Tasks\Messaging;

use Illuminate\Support\Facades\Log;

/** The default gateway: writes the message to the log instead of sending it, so nothing leaves the system until a real provider is configured. */
class LogMessageGateway implements MessageGateway
{
    public function send(string $channel, string $to, string $body): void
    {
        Log::info("[{$channel}] to {$to}: {$body}");
    }
}
