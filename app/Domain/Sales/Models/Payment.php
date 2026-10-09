<?php

namespace App\Domain\Sales\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\ReceiptMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Only voiding may change a payment. */
    public function mutableColumns(): array
    {
        return ['voided_at', 'voided_by', 'void_reason'];
    }

    protected function casts(): array
    {
        return ['is_historical' => 'boolean', 'method' => ReceiptMethod::class, 'received_on' => 'date', 'amount_minor' => 'integer', 'voided_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Received money not yet allocated to an invoice: what the customer still has on deposit. */
    public function unallocatedMinor(): int
    {
        return $this->isVoided() ? 0 : $this->amount_minor - (int) $this->allocations()->sum('amount_minor');
    }
}
