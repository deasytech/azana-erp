<?php

namespace App\Domain\Tasks\Models;

use App\Domain\System\Concerns\ImmutableRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo or file showing a task was done. Kept for good once added. */
class TaskEvidence extends Model
{
    use ImmutableRecord;

    public const UPDATED_AT = null;

    protected $table = 'task_evidence';

    protected $guarded = [];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
