<?php

namespace App\Domain\Backup\Notifications;

use App\Domain\Backup\Data\StatusCheck;
use App\Domain\Farm\Actions\ResolveSettings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the people who look after the system that something it depends on has stopped working. */
class OperationsAlert extends Notification
{
    /** @param list<StatusCheck> $problems */
    public function __construct(public readonly array $problems) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(ResolveSettings::class)->get('notifications.email_enabled') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    private function body(): string
    {
        return implode("\n", array_map(fn (StatusCheck $c) => "{$c->name}: {$c->detail}", $this->problems));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return ['format' => 'filament', 'title' => 'The system needs attention', 'body' => $this->body(), 'status' => 'danger', 'duration' => 'persistent', 'actions' => [], 'icon' => 'heroicon-o-exclamation-triangle', 'iconColor' => 'danger', 'view' => 'filament-notifications::notification', 'viewData' => [], 'color' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Azana ERP needs attention')->line('These checks are failing:')->line($this->body());
    }
}
