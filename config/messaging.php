<?php

return [
    /*
     * The gateway that delivers SMS and WhatsApp alerts. "log" only writes them to the log; to send for real, bind your provider's
     * App\Domain\Tasks\Messaging\MessageGateway implementation in a service provider and set the driver to its name.
     */
    'driver' => env('MESSAGING_DRIVER', 'log'),
];
