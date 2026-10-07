<?php

namespace App\Domain\Tasks\Notifications;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Tasks\Models\Task;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells a person a task has been handed to them: in the bell, and by email if that is switched on. */
class TaskAssignedNotification extends Notification
{
    public function __construct(public readonly Task $task) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return app(ResolveSettings::class)->get('notifications.email_enabled') && $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    private function line(): string
    {
        return "{$this->task->number}: {$this->task->title} (due {$this->task->due_on->format('d M Y')})";
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return ['format' => 'filament', 'title' => 'A task was assigned to you', 'body' => $this->line(), 'status' => 'info', 'duration' => 'persistent', 'actions' => [], 'icon' => 'heroicon-o-clipboard-document-check', 'iconColor' => 'info', 'view' => 'filament-notifications::notification', 'viewData' => [], 'color' => null, 'task_id' => $this->task->id];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('A task was assigned to you')->line($this->line());
    }
}
