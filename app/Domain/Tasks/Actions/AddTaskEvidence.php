<?php

namespace App\Domain\Tasks\Actions;

use App\Domain\System\Exceptions\DomainException;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskEvidence;
use App\Models\User;

/** Attaches a stored photo or file to a task. The path must be one the upload form stored under task-evidence/. */
class AddTaskEvidence
{
    public function __construct(private readonly AdvanceTask $advance) {}

    public function __invoke(Task $task, string $path, ?string $caption, User $actor): TaskEvidence
    {
        if (! str_starts_with($path, 'task-evidence/') || str_contains($path, '..')) {
            throw new DomainException('That file was not uploaded for a task.', 'evidence_path');
        }

        $this->advance->mayWork($task, $actor) || throw new DomainException("You may not add evidence to {$task->number}: it is not yours.", 'task_forbidden');

        return $task->evidence()->create(['path' => $path, 'caption' => filled($caption) ? trim($caption) : null, 'uploaded_by' => $actor->id]);
    }
}
