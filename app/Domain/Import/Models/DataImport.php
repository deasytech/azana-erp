<?php

namespace App\Domain\Import\Models;

use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataImport extends Model
{
    use Auditable;

    public const CHECKED = 'checked';

    public const COMMITTING = 'committing';

    public const COMMITTED = 'committed';

    public const DISCARDED = 'discarded';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['total_rows' => 'integer', 'error_rows' => 'integer', 'committed_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataImportRow::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function committer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by');
    }

    public function isReady(): bool
    {
        return $this->status === self::CHECKED && $this->error_rows === 0 && $this->total_rows > 0;
    }
}
