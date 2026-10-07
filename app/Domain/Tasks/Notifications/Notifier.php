<?php

namespace App\Domain\Tasks\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Throwable;

/** Sends a notification without letting a mail or messaging failure undo the work that caused it (the failure is reported, not thrown). */
class Notifier
{
    public function send(User $user, Notification $notification): bool
    {
        try {
            $user->notify($notification);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
