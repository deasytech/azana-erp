<?php

namespace App\Domain\Health\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_reportable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function isInUse(): bool
    {
        return HealthEvent::where('disease_id', $this->id)->exists() || MortalityRecord::where('disease_id', $this->id)->exists();
    }
}
