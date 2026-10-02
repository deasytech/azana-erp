<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Actions\NextNumber;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\StockCountStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Opens a count of one store and lists what the system says is there (item and batch, by quantity) so the
 * counted quantities can be entered against it. A store can have only one count in progress.
 */
class StartStockCount
{
    public function __construct(private readonly NextNumber $nextNumber) {}

    public function __invoke(InventoryLocation|int $location, CarbonInterface $countedOn, ?string $notes = null, ?User $actor = null): StockCount
    {
        return DB::transaction(function () use ($location, $countedOn, $notes, $actor) {
            $location = InventoryLocation::lockForUpdate()->findOrFail($location instanceof InventoryLocation ? $location->id : $location);

            if (! $location->is_active) {
                throw new DomainException('Choose an active store.', 'inactive_stock_target');
            }

            if ($countedOn->gt(now()->addMinutes(5))) {
                throw new DomainException('A count cannot be dated in the future.', 'count_future');
            }

            if (StockCount::where('inventory_location_id', $location->id)->whereIn('status', [StockCountStatus::Draft, StockCountStatus::Submitted])->exists()) {
                throw new DomainException("{$location->name} already has a count in progress.", 'count_in_progress');
            }

            $count = StockCount::create([
                'number' => sprintf('SC-%06d', ($this->nextNumber)('stock_count')),
                'inventory_location_id' => $location->id,
                'counted_on' => $countedOn,
                'notes' => $notes,
                'started_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            InventoryLayer::where('inventory_location_id', $location->id)->where('remaining_quantity', '>', 0)
                ->selectRaw('inventory_item_id, inventory_batch_id, SUM(remaining_quantity) as on_hand')
                ->groupBy('inventory_item_id', 'inventory_batch_id')->get()
                ->each(fn ($row) => $count->lines()->create([
                    'inventory_item_id' => $row->inventory_item_id,
                    'inventory_batch_id' => $row->inventory_batch_id,
                    'system_quantity' => $row->on_hand,
                ]));

            return $count->load('lines');
        });
    }
}
