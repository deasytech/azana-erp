<?php

namespace App\Domain\Biosecurity\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class BiosecurityChecklistItem extends Model
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
        return BiosecurityCheckItem::where('checklist_item_id', $this->id)->exists();
    }
}
