<?php

namespace App\Domain\Finance\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\JournalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A balanced set of lines. Once posted it never changes; a mistake is undone by a reversing entry. */
class JournalEntry extends Model
{
    use Auditable, ImmutableRecord;

    protected $guarded = [];

    /** Only the approval decision may change an entry. */
    public function mutableColumns(): array
    {
        return ['status', 'decided_by', 'decided_at', 'decision_notes', 'updated_at'];
    }

    protected function casts(): array
    {
        return ['status' => JournalStatus::class, 'entry_date' => 'date', 'total_minor' => 'integer', 'decided_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
