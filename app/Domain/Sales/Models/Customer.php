<?php

namespace App\Domain\Sales\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\Farm\Models\LookupValue;
use App\Domain\System\Concerns\Auditable;
use App\Enums\CreditStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
    use Auditable, HasBusinessCode;

    protected $guarded = [];

    protected $attributes = ['is_active' => true, 'credit_status' => 'none', 'credit_limit_minor' => 0, 'payment_terms_days' => 0];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_status' => CreditStatus::class,
            'credit_limit_minor' => 'integer',
            'payment_terms_days' => 'integer',
            'credit_approved_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LookupValue::class, 'customer_type_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoiceLines(): HasManyThrough
    {
        return $this->hasManyThrough(InvoiceLine::class, Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function creditApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_approved_by');
    }

    /** A customer with any sales history can be deactivated but not deleted. */
    public function isInUse(): bool
    {
        return $this->orders()->exists() || $this->payments()->exists();
    }
}
