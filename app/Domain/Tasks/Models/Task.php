<?php

namespace App\Domain\Tasks\Models;

use App\Domain\System\Concerns\Auditable;
use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use Auditable;

    protected $guarded = [];

    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'category' => TaskCategory::class, 'priority' => TaskPriority::class, 'status' => TaskStatus::class,
            'due_on' => 'date', 'requires_evidence' => 'boolean', 'completed_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TaskEvidence::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [TaskStatus::Open, TaskStatus::InProgress], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on->lt(now()->startOfDay());
    }
}
