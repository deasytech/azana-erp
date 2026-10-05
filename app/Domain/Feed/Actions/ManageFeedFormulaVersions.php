<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FormulaStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** The life of a formula: activate a finished draft, retire an active one, or start a new version of it. */
class ManageFeedFormulaVersions
{
    /** Makes a draft the active formula for its code (any other active version is retired). */
    public function activate(FeedFormula $formula, ?User $actor = null): FeedFormula
    {
        return DB::transaction(function () use ($formula, $actor) {
            $formula = FeedFormula::lockForUpdate()->with('items.item')->findOrFail($formula->id);

            $formula->isDraft() || throw new DomainException('Only a draft formula can be activated.', 'formula_not_draft');
            $this->assertComplete($formula);

            FeedFormula::where('code', $formula->code)->where('status', FormulaStatus::Active)->update(['status' => FormulaStatus::Retired]);
            $formula->update(['status' => FormulaStatus::Active, 'activated_by' => ($actor ?? Auth::user())?->getKey(), 'activated_at' => now()]);

            return $formula;
        });
    }

    public function retire(FeedFormula $formula): FeedFormula
    {
        return DB::transaction(function () use ($formula) {
            $formula = FeedFormula::lockForUpdate()->findOrFail($formula->id);
            $formula->status === FormulaStatus::Active || throw new DomainException('Only an active formula can be retired.', 'formula_not_active');
            $formula->update(['status' => FormulaStatus::Retired]);

            return $formula;
        });
    }

    /** A new draft copied from this formula, one version higher than the latest. */
    public function newVersion(FeedFormula $formula, ?User $actor = null): FeedFormula
    {
        return DB::transaction(function () use ($formula, $actor) {
            $source = FeedFormula::with('items')->findOrFail($formula->id);
            $latest = (int) FeedFormula::where('code', $source->code)->lockForUpdate()->max('version');

            if (FeedFormula::where('code', $source->code)->where('status', FormulaStatus::Draft)->exists()) {
                throw new DomainException('This formula already has a draft version; finish or delete it first.', 'draft_exists');
            }

            $copy = $source->replicate(['status', 'version', 'activated_by', 'activated_at'])->fill([
                'status' => FormulaStatus::Draft, 'version' => $latest + 1, 'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);
            $copy->code = $source->code;
            $copy->save();

            foreach ($source->items as $item) {
                $copy->items()->create($item->only(['inventory_item_id', 'inclusion_percent', 'notes']));
            }

            return $copy->load('items');
        });
    }

    private function assertComplete(FeedFormula $formula): void
    {
        $total = $formula->items->reduce(fn (string $sum, $item) => bcadd($sum, (string) $item->inclusion_percent, 4), '0');

        if (bccomp($total, '100', 4) !== 0) {
            throw new DomainException("The ingredients add up to {$total}%, not 100%.", 'formula_total');
        }

        if ($formula->items->contains(fn ($item) => ! $item->item->is_active)) {
            throw new DomainException('Every ingredient must be an active stock item.', 'inactive_ingredient');
        }

        if (! InventoryItem::where('feed_type_id', $formula->feed_type_id)->where('is_active', true)->exists()) {
            throw new DomainException('Link a stock item to this formula\'s feed type first, so the finished feed has somewhere to be stocked.', 'feed_not_stocked');
        }
    }
}
