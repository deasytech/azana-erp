<?php

namespace App\Domain\Mobile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One mutation a mobile device sent, and what the server did with it. */
class SyncMutation extends Model
{
    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    public const CONFLICT = 'conflict';

    public const FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array', 'errors' => 'array', 'occurred_at' => 'datetime', 'attempted_at' => 'datetime',
            'synced_at' => 'datetime', 'reviewed_at' => 'datetime', 'attempts' => 'integer', 'server_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** A final answer the device must not send again (a failed one may be retried with the same client_id). */
    public function isFinal(): bool
    {
        return $this->status !== self::FAILED;
    }
}
