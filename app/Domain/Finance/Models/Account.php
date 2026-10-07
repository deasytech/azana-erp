<?php

namespace App\Domain\Finance\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['type' => AccountType::class, 'is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function label(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /** The account the operational postings use for this role (e.g. 'receivables'). */
    public static function system(string $key): self
    {
        return static::where('system_key', $key)->firstOrFail();
    }
}
