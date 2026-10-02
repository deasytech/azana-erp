<?php

namespace App\Domain\Supplier\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'payment_terms_days' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'payment_terms_days' => 'integer'];
    }

    /** Suppliers with stock history can be deactivated but never deleted. */
    public function isInUse(): bool
    {
        return InventoryBatch::where('supplier_id', $this->id)->exists();
    }
}
