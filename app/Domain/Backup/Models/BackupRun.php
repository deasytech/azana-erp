<?php

namespace App\Domain\Backup\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One backup or restore test. Rows are never edited after they finish. */
class BackupRun extends Model
{
    public const BACKUP = 'backup';

    public const RESTORE_TEST = 'restore_test';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['offsite' => 'boolean', 'size_bytes' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'pruned_at' => 'datetime'];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backup_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function succeeded(): bool
    {
        return $this->status === 'success';
    }
}
