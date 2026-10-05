<?php

namespace App\Domain\Procurement\Models;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Concerns\ImmutableRecord;
use App\Enums\PaymentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    use Auditable, ImmutableRecord;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** Only voiding may change an invoice. */
    public function mutableColumns(): array
    {
        return ['voided_at', 'voided_by', 'void_reason'];
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /** Minor units paid so far (payments that are paid and not voided). */
    public function paidMinor(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments
                ->filter(fn (SupplierPayment $payment) => $payment->status === PaymentStatus::Paid)
                ->sum('amount_minor');
        }

        return (int) $this->payments()->where('status', PaymentStatus::Paid)->sum('amount_minor');
    }

    /** Minor units paid or waiting for approval (what is already committed against the invoice). */
    public function committedMinor(): int
    {
        return (int) $this->payments()->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PendingApproval])->sum('amount_minor');
    }

    public function balanceMinor(): int
    {
        return $this->isVoided() ? 0 : $this->total_minor - $this->paidMinor();
    }

    public function isOverdue(): bool
    {
        return ! $this->isVoided() && $this->balanceMinor() > 0 && $this->due_date->lt(now()->startOfDay());
    }
}
