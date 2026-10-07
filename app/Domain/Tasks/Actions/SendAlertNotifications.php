<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\Tasks\Notifications\AlertNotification;
use App\Domain\Tasks\Notifications\Notifier;
use App\Models\User;

/**
 * Tells each person about the critical (danger) alerts in the areas they may view, as one digest. An alert already sent to someone
 * today is not sent to them again, so running this every hour does not repeat itself. Returns how many people were notified.
 */
class SendAlertNotifications
{
    public function __construct(private readonly GetAlerts $alerts, private readonly Notifier $notifier) {}

    public function __invoke(): int
    {
        $critical = ($this->alerts)()->where('severity', 'danger');

        if ($critical->isEmpty()) {
            return 0;
        }

        $sent = 0;

        foreach (User::where('is_active', true)->get() as $user) {
            $already = $user->notifications()->where('type', AlertNotification::class)->where('created_at', '>=', now()->startOfDay())->get()
                ->flatMap(fn ($n) => $n->data['alert_keys'] ?? [])->all();

            $new = $critical->filter(fn ($a) => $user->can("{$a['module']}.view") && ! in_array($a['key'], $already, true))->take(20)
                ->map(fn ($a) => ['key' => $a['key'], 'message' => $a['message']])->values()->all();

            $new !== [] && $this->notifier->send($user, new AlertNotification($new)) && $sent++;
        }

        return $sent;
    }
}
