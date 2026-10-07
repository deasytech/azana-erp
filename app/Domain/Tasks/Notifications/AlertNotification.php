<?php

namespace App\Domain\Tasks\Notifications;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Tasks\Messaging\MessagingChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A digest of critical alerts for one person: in the bell, and by email, SMS or WhatsApp where those are switched on and the person can be reached. */
class AlertNotification extends Notification
{
    /** @param list<array{key: string, message: string}> $alerts */
    public function __construct(public readonly array $alerts) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $settings = app(ResolveSettings::class);

        return array_values(array_filter([
            'database',
            $settings->get('notifications.email_enabled') && $notifiable->email ? 'mail' : null,
            $this->messageChannels($notifiable) ? MessagingChannel::class : null,
        ]));
    }

    /** @return list<'sms'|'whatsapp'> */
    public function messageChannels(object $notifiable): array
    {
        $settings = app(ResolveSettings::class);

        return $notifiable->phone ? array_values(array_filter([
            $settings->get('notifications.sms_enabled') ? 'sms' : null,
            $settings->get('notifications.whatsapp_enabled') ? 'whatsapp' : null,
        ])) : [];
    }

    public function messageText(): string
    {
        return $this->title().': '.implode('; ', array_column($this->alerts, 'message'));
    }

    private function title(): string
    {
        return count($this->alerts) === 1 ? 'Critical alert' : count($this->alerts).' critical alerts';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => implode("\n", array_column($this->alerts, 'message')), 'alert_keys' => array_column($this->alerts, 'key')];
    }

    /** What Filament shows in the bell. */
    public function toDatabase(object $notifiable): array
    {
        return ['format' => 'filament', 'title' => $this->title(), 'body' => implode("\n", array_column($this->alerts, 'message')), 'status' => 'danger', 'duration' => 'persistent', 'actions' => [], 'icon' => 'heroicon-o-exclamation-triangle', 'iconColor' => 'danger', 'view' => 'filament-notifications::notification', 'viewData' => [], 'color' => null] + $this->toArray($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title().' - Princess Azana Farms')->line($this->title().':')
            ->line(implode("\n", array_map(fn ($a) => '- '.$a['message'], $this->alerts)));
    }
}
