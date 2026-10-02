<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryLayer;
use Illuminate\Support\Collection;

/** Batches that still hold stock and are expired or expire within the warning period (`inventory.expiry_warning_days`). */
class GetExpiryAlerts
{
    public function __construct(private readonly ResolveSettings $settings) {}

    /** @return Collection<int, array{batch: InventoryBatch, on_hand: string, days_left: int, expired: bool}> */
    public function __invoke(): Collection
    {
        $limit = now()->startOfDay()->addDays((int) $this->settings->get('inventory.expiry_warning_days'));
        $held = InventoryLayer::where('remaining_quantity', '>', 0)->whereNotNull('inventory_batch_id')
            ->selectRaw('inventory_batch_id, SUM(remaining_quantity) as on_hand')->groupBy('inventory_batch_id')->pluck('on_hand', 'inventory_batch_id');

        return InventoryBatch::with('item.unit')->whereIn('id', $held->keys())->whereDate('expiry_date', '<=', $limit)->orderBy('expiry_date')->get()
            ->map(fn (InventoryBatch $batch) => [
                'batch' => $batch,
                'on_hand' => bcadd((string) $held[$batch->id], '0', 3),
                'days_left' => (int) now()->startOfDay()->diffInDays($batch->expiry_date, false),
                'expired' => $batch->isExpiredOn(now()),
            ]);
    }
}
