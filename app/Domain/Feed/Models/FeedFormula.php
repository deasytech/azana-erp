<?php

namespace App\Domain\Feed\Models;

use App\Domain\Farm\Concerns\HasBusinessCode;
use App\Domain\System\Concerns\Auditable;
use App\Enums\FormulaStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedFormula extends Model
{
    use Auditable, HasBusinessCode;

    /** The nutritional specification columns, with their labels. */
    public const NUTRITION = [
        'crude_protein_percent' => 'Crude protein (%)', 'crude_fibre_percent' => 'Crude fibre (%)', 'crude_fat_percent' => 'Crude fat (%)',
        'calcium_percent' => 'Calcium (%)', 'phosphorus_percent' => 'Phosphorus (%)', 'lysine_percent' => 'Lysine (%)',
        'energy_kcal_per_kg' => 'Energy (kcal/kg)',
    ];

    protected $guarded = [];

    protected $attributes = ['status' => 'draft', 'version' => 1, 'process_loss_percent' => 0];

    protected function casts(): array
    {
        return [
            'status' => FormulaStatus::class,
            'version' => 'integer',
            'process_loss_percent' => 'decimal:2',
            'activated_at' => 'datetime',
        ] + array_fill_keys(array_keys(self::NUTRITION), 'decimal:2');
    }

    public function feedType(): BelongsTo
    {
        return $this->belongsTo(FeedType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeedFormulaItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FeedProductionOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === FormulaStatus::Draft;
    }

    /** Formulas that have been used by an order are history and cannot be deleted. */
    public function isInUse(): bool
    {
        return $this->status !== FormulaStatus::Draft || $this->orders()->exists();
    }
}
