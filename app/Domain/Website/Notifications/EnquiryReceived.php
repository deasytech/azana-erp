<?php

namespace App\Domain\Website\Notifications;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Website\Models\Enquiry;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells staff a visitor has asked for something: in the bell, and by email if that is switched on. */
class EnquiryReceived extends Notification
{
    public function __construct(public readonly Enquiry $enquiry) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return app(ResolveSettings::class)->get('notifications.email_enabled') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    private function line(): string
    {
        return "{$this->enquiry->number} from {$this->enquiry->name} about {$this->enquiry->kind->label()}";
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return ['format' => 'filament', 'title' => 'New website enquiry', 'body' => $this->line(), 'status' => 'info', 'duration' => 'persistent', 'actions' => [], 'icon' => 'heroicon-o-envelope', 'iconColor' => 'info', 'view' => 'filament-notifications::notification', 'viewData' => [], 'color' => null, 'enquiry_id' => $this->enquiry->id];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('New website enquiry '.$this->enquiry->number)
            ->line($this->line())
            ->line("Phone: {$this->enquiry->phone}  Email: {$this->enquiry->email}")
            ->line($this->enquiry->message);
    }
}
