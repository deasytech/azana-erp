<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Models\Task;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Creates a task with a deadline. It may be handed to one person straight away, or left for whoever holds a role to pick up.
 * source_key names what it came from (an alert, a schedule), so generating the same task twice makes one.
 */
class CreateTask
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly AssignTask $assign) {}

    public function __invoke(string $title, CarbonInterface $dueOn, TaskCategory $category = TaskCategory::General, TaskPriority $priority = TaskPriority::Normal, ?string $description = null, User|int|null $assignee = null, ?string $role = null, ?string $sourceKey = null, bool $requiresEvidence = false, ?User $actor = null): Task
    {
        if (trim($title) === '' || mb_strlen($title) > 255) {
            throw new DomainException('Give the task a title of up to 255 characters.', 'task_title');
        }

        if ($dueOn->gt(now()->addYear())) {
            throw new DomainException('A task cannot be due more than a year ahead.', 'task_due');
        }

        if (filled($role) && ! Role::where('name', $role)->exists()) {
            throw new DomainException("There is no role called {$role}.", 'task_role');
        }

        try {
            $task = $this->write($title, $dueOn, $category, $priority, $description, $role, $sourceKey, $requiresEvidence, $actor);
        } catch (UniqueConstraintViolationException $e) {
            // Only a clash on source_key (another process made this very task first) is a repeat; any other unique failure is a real error.
            $task = ($sourceKey ? Task::firstWhere('source_key', $sourceKey) : null) ?? throw $e;
        }

        return $task->wasRecentlyCreated && $assignee ? ($this->assign)($task, $assignee, $actor) : $task;
    }

    private function write(string $title, CarbonInterface $dueOn, TaskCategory $category, TaskPriority $priority, ?string $description, ?string $role, ?string $sourceKey, bool $requiresEvidence, ?User $actor): Task
    {
        return DB::transaction(function () use ($title, $dueOn, $category, $priority, $description, $role, $sourceKey, $requiresEvidence, $actor) {
            if ($sourceKey && ($existing = Task::firstWhere('source_key', $sourceKey))) {
                return $existing;
            }

            return Task::create([
                'number' => sprintf('TK-%06d', ($this->nextNumber)('task')), 'title' => trim($title), 'description' => $description,
                'category' => $category, 'priority' => $priority, 'due_on' => $dueOn, 'responsible_role' => $role ?: null,
                'source_key' => $sourceKey, 'requires_evidence' => $requiresEvidence, 'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
        });
    }
}
