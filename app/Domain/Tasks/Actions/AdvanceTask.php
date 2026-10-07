<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a task along: start, complete (with notes, and evidence where the task asks for it) or cancel (with a reason).
 * A task given to a person is worked by that person or by a supervisor (tasks.approve); an unassigned one by anyone with
 * tasks.edit who holds its responsible role, or a supervisor.
 */
class AdvanceTask
{
    public function start(Task $task, User $actor): Task
    {
        return $this->move($task, $actor, fn (Task $t) => $t->status === TaskStatus::Open ? ['status' => TaskStatus::InProgress] : throw new DomainException("{$t->number} has already been started.", 'task_state'));
    }

    public function complete(Task $task, User $actor, ?string $notes = null): Task
    {
        return $this->move($task, $actor, function (Task $t) use ($actor, $notes) {
            if ($t->requires_evidence && ! $t->evidence()->exists()) {
                throw new DomainException("{$t->number} needs a photo or file showing it was done before it can be completed.", 'task_evidence');
            }

            return ['status' => TaskStatus::Done, 'completed_by' => $actor->id, 'completed_at' => now(), 'completion_notes' => filled($notes) ? trim($notes) : null];
        });
    }

    public function cancel(Task $task, User $actor, string $reason): Task
    {
        trim($reason) === '' && throw new DomainException('A reason is required to cancel a task.', 'reason_required');

        return $this->move($task, $actor, fn () => ['status' => TaskStatus::Cancelled, 'cancel_reason' => trim($reason)]);
    }

    public function mayWork(Task $task, User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->can('tasks.approve')
            || ($task->assigned_to !== null && $task->assigned_to === $user->id)
            || ($task->assigned_to === null && $user->can('tasks.edit') && (blank($task->responsible_role) || $user->hasRole($task->responsible_role)));
    }

    /** @param callable(Task): array<string, mixed> $changes */
    private function move(Task $task, User $actor, callable $changes): Task
    {
        return DB::transaction(function () use ($task, $actor, $changes) {
            $task = Task::lockForUpdate()->findOrFail($task->id);

            $this->mayWork($task, $actor) || throw new DomainException("You may not work on {$task->number}: it is not yours.", 'task_forbidden');
            $task->isOpen() || throw new DomainException("{$task->number} is already {$task->status->value}.", 'task_state');

            $task->update($changes($task));

            return $task;
        });
    }
}
