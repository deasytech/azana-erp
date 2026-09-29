<?php

namespace App\Domain\System\Concerns;

use App\Domain\System\Actions\RecordAudit;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Records created / updated / deleted events with old and new values. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => app(RecordAudit::class)('created', $model, null, $model->getAttributes()));

        static::updated(function ($model) {
            $changes = collect($model->getChanges())->except('updated_at')->all();

            if ($changes === []) {
                return;
            }

            $old = collect($changes)->map(fn ($v, $k) => $model->getOriginal($k))->all();

            app(RecordAudit::class)('updated', $model, $old, $changes);
        });

        static::deleted(fn ($model) => app(RecordAudit::class)('deleted', $model, $model->getAttributes()));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
