<?php

namespace App\Filament\Support;

use App\Domain\System\Exceptions\DomainException;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Runs a domain action from a Filament button, turning rule violations into a notification. */
class DomainAction
{
    public static function run(Closure $callback, Action $action, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $e) {
            Notification::make()->title('Not saved')->body($e->getMessage())->danger()->send();
            $action->halt();
        }

        Notification::make()->title($success)->success()->send();
    }
}
