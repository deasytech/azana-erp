<?php

namespace App\Domain\Mobile\Exceptions;

use App\Domain\System\Exceptions\DomainException;

/** What the device saw is no longer what the server holds: reported as a conflict, never applied over the newer data. */
class StaleData extends DomainException {}
