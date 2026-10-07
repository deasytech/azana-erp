<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tasks\Models\Task;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /** The worker's open tasks, soonest due first; complete one with the complete_task quick action. */
    public function mine(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('tasks.view'), 403);

        $tasks = Task::where('assigned_to', $request->user()->id)->whereIn('status', [TaskStatus::Open, TaskStatus::InProgress])->orderBy('due_on')->limit(200)->get();

        return response()->json(['data' => $tasks->map(fn (Task $t) => [
            'number' => $t->number, 'title' => $t->title, 'description' => $t->description, 'category' => $t->category->value, 'priority' => $t->priority->value,
            'status' => $t->status->value, 'due_on' => $t->due_on->toDateString(), 'overdue' => $t->isOverdue(), 'requires_evidence' => $t->requires_evidence,
        ])->all()]);
    }
}
