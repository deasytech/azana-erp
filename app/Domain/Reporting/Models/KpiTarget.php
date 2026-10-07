<?php

namespace App\Domain\Reporting\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class KpiTarget extends Model
{
    use Auditable;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['year' => 'integer', 'month' => 'integer', 'target_value' => 'decimal:4'];
    }
}
