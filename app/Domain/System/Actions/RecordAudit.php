<?php

namespace App\Domain\System\Actions;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Single entry point for writing audit records, used by the Auditable trait
 * and directly by domain actions (approvals, reversals, adjustments...).
 */
class RecordAudit
{
    /** Attribute names that must never be written to the trail. */
    private const REDACTED = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function __invoke(
        string $event,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
        User|int|null $approvedBy = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->redact($old),
            'new_values' => $this->redact($new),
            'reason' => $reason,
            'approved_by' => $approvedBy instanceof User ? $approvedBy->getKey() : $approvedBy,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'device_id' => $request?->header('X-Device-Id'),
            'url' => $request ? mb_substr($request->fullUrl(), 0, 2048) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::REDACTED as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
