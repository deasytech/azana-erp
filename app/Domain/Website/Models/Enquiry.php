<?php

namespace App\Domain\Website\Models;

use App\Domain\Sales\Models\Customer;
use App\Domain\System\Concerns\Auditable;
use App\Enums\EnquiryKind;
use App\Enums\EnquiryStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    use Auditable;

    protected $table = 'website_enquiries';

    protected $guarded = [];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['kind' => EnquiryKind::class, 'status' => EnquiryStatus::class, 'handled_at' => 'datetime'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
