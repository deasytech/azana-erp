<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedProductionOrder;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FormulaStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Plans a batch of feed: how much finished feed to make from an active formula, from which store the materials
 * come and where the finished feed goes. The materials the formula needs are calculated now and kept on the
 * order, so later changes to the formula never alter it.
 */
class CreateFeedProductionOrder
{
    public function __construct(private readonly NextNumber $nextNumber, private readonly CalculateFeedRequirements $requirements) {}

    public function __invoke(FeedFormula|int $formula, string $plannedOutputKg, CarbonInterface $plannedOn, InventoryLocation|int $sourceLocation, InventoryLocation|int $outputLocation, ?string $notes = null, ?User $actor = null): FeedProductionOrder
    {
        return DB::transaction(function () use ($formula, $plannedOutputKg, $plannedOn, $sourceLocation, $outputLocation, $notes, $actor) {
            $formula = FeedFormula::findOrFail($formula instanceof FeedFormula ? $formula->id : $formula);
            $source = InventoryLocation::findOrFail($sourceLocation instanceof InventoryLocation ? $sourceLocation->id : $sourceLocation);
            $output = InventoryLocation::findOrFail($outputLocation instanceof InventoryLocation ? $outputLocation->id : $outputLocation);

            $this->validate($formula, $source, $output);

            $needed = ($this->requirements)($formula, $plannedOutputKg)->filter(fn (array $row) => bccomp($row['quantity'], '0', 3) > 0);

            if ($needed->isEmpty()) {
                throw new DomainException('That amount is too small to need any material.', 'output_kg');
            }

            $order = FeedProductionOrder::create([
                'number' => sprintf('FM-%06d', ($this->nextNumber)('feed_production')),
                'feed_formula_id' => $formula->id,
                'planned_output_kg' => $plannedOutputKg,
                'planned_on' => $plannedOn,
                'source_location_id' => $source->id,
                'output_location_id' => $output->id,
                'notes' => $notes,
                'created_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            foreach ($needed as $row) {
                $order->lines()->create(['inventory_item_id' => $row['item']->id, 'planned_quantity' => $row['quantity']]);
            }

            return $order->load('lines');
        });
    }

    private function validate(FeedFormula $formula, InventoryLocation $source, InventoryLocation $output): void
    {
        if ($formula->status !== FormulaStatus::Active) {
            throw new DomainException("{$formula->code} v{$formula->version} is not an active formula.", 'formula_not_active');
        }

        if (! $source->is_active || ! $output->is_active) {
            throw new DomainException('Choose active stores.', 'inactive_stock_target');
        }

        if (! InventoryItem::where('feed_type_id', $formula->feed_type_id)->where('is_active', true)->exists()) {
            throw new DomainException('The feed type has no stock item to hold the finished feed.', 'feed_not_stocked');
        }
    }
}
