<?php

namespace App\Domain\Feed\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class FeedType extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isInUse(): bool
    {
        return FeedConsumptionRecord::where('feed_type_id', $this->id)->exists();
    }
}
