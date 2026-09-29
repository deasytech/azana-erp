<?php

namespace App\Domain\Farm\Models;

use App\Domain\Farm\Settings\SettingDefinition;
use App\Domain\Farm\Settings\SettingDefinitions;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmSetting extends Model
{
    use Auditable;

    protected $guarded = [];

    public function definition(): ?SettingDefinition
    {
        return SettingDefinitions::all()[$this->key] ?? null;
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function isInUse(): bool
    {
        return false;
    }
}
