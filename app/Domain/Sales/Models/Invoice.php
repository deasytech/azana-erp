<?php

namespace App\Domain\Sales\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'total_minor' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** Minor units paid, from payments that have not been voided. */
    public function paidMinor(): int
    {
        // Lists load the allocations and their payments up front; use them rather than asking the database again.
        if ($this->relationLoaded('allocations') && $this->allocations->every(fn (PaymentAllocation $a) => $a->relationLoaded('payment'))) {
            return (int) $this->allocations->reject(fn (PaymentAllocation $a) => $a->payment->isVoided())->sum('amount_minor');
        }

        return (int) $this->allocations()->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))->sum('amount_minor');
    }

    public function balanceMinor(): int
    {
        return $this->total_minor - $this->paidMinor();
    }

    public function isOverdue(): bool
    {
        return $this->balanceMinor() > 0 && $this->due_on->lt(now()->startOfDay());
    }
}
