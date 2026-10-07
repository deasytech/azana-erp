<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Notifications\Notifier;
use App\Domain\Tasks\Notifications\TaskAssignedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Hands an open task to one person (and tells them). Earlier assignees stay in the task's history. */
class AssignTask
{
    public function __construct(private readonly Notifier $notifier) {}

    public function __invoke(Task $task, User|int $user, ?User $actor = null, ?string $note = null): Task
    {
        $user = User::findOrFail($user instanceof User ? $user->id : $user);
        $actor ??= Auth::user();
        $user->is_active || throw new DomainException("{$user->name} is not an active user.", 'task_assignee');

        $task = DB::transaction(function () use ($task, $user, $actor, $note) {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            $task->isOpen() || throw new DomainException("{$task->number} is {$task->status->value} and cannot be assigned.", 'task_state');

            if ($task->assigned_to === $user->id) {
                return $task;
            }

            $task->update(['assigned_to' => $user->id]);
            $task->assignments()->create(['user_id' => $user->id, 'assigned_by' => $actor?->id, 'note' => $note]);
            $task->wasChanged('assigned_to') && $task->setAttribute('just_assigned', true);

            return $task;
        });

        // Telling people someone else gave them work; handing a task to yourself needs no message.
        $task->getAttribute('just_assigned') && $actor?->id !== $user->id && $this->notifier->send($user, new TaskAssignedNotification($task));

        return $task;
    }
}
