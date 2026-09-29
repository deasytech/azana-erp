<?php

namespace App\Filament\Concerns;

use App\Domain\System\Exceptions\DomainException;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Turns business-rule violations into a notification instead of an error page. */
trait NotifiesDomainErrors
{
    protected function notifyFailure(DomainException $e): void
    {
        Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
    }

    /** Runs the callback; on a rule violation, shows it and halts the given action (or the page form). */
    protected function attempt(Closure $callback, ?Action $action = null): mixed
    {
        try {
            return $callback();
        } catch (DomainException $e) {
            $this->notifyFailure($e);
            $action ? $action->halt() : $this->halt();
        }

        return null;
    }
}
