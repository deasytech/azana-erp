<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLocation;
use App\Domain\Semen\Events\SemenBatchReleased;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\System\Actions\AssertMayDecide;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType;
use App\Enums\Module;
use App\Enums\SemenBatchStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Releases a processed batch that passed QC for sale and use: its doses become stock of the breed's semen item,
 * by batch and expiry. Needs semen.approve and, by default, someone other than the analyst who recorded the QC.
 */
class ReleaseSemenBatch
{
    public function __construct(
        private readonly ReceiveStock $receiveStock,
        private readonly AssertMayDecide $mayDecide,
        private readonly ResolveSettings $settings,
    ) {}

    public function __invoke(SemenBatch $batch, User $approver, InventoryLocation|int $location, ?string $notes = null): SemenBatch
    {
        return DB::transaction(function () use ($batch, $approver, $location, $notes) {
            $batch = SemenBatch::lockForUpdate()->with(['qcRecords', 'breed'])->findOrFail($batch->id);

            $this->validate($batch);
            ($this->mayDecide)($approver, $batch->qcRecords->sortByDesc('id')->first()?->evaluated_by, Module::Semen, 'semen batch release');

            $item = $this->stockItem($batch);
            $received = ($this->receiveStock)(InventoryTransactionType::Production, $item, $location, (string) $batch->doses_produced, now()->startOfDay(), [
                'value_minor' => $batch->doses_produced * (int) $this->settings->get('semen.cost_per_dose_minor'),
                'batch_number' => $batch->number,
                'expiry_date' => $batch->expiry_date,
                'source_type' => 'semen_batch',
                'source_id' => $batch->id,
                'reason' => "Released {$batch->number}".($notes ? ": {$notes}" : ''),
            ], $approver);

            $batch->update([
                'status' => SemenBatchStatus::Released, 'inventory_batch_id' => $received->inventory_batch_id,
                'released_by' => $approver->id, 'released_at' => now(),
            ]);
            SemenBatchReleased::dispatch($batch);

            return $batch;
        });
    }

    private function validate(SemenBatch $batch): void
    {
        if ($batch->status !== SemenBatchStatus::Passed) {
            throw new DomainException("{$batch->number} is {$batch->status->label()}: only a batch that passed QC can be released.", 'batch_not_passed');
        }

        if ($batch->doses_produced === null) {
            throw new DomainException('Record how many doses the batch was made into before releasing it.', 'batch_not_processed');
        }

        if ($batch->isExpired()) {
            throw new DomainException("{$batch->number} has expired.", 'batch_expired');
        }
    }

    private function stockItem(SemenBatch $batch): InventoryItem
    {
        $item = $batch->breed_id ? InventoryItem::where('breed_id', $batch->breed_id)->where('is_active', true)->first() : null;

        if (! $item) {
            throw new DomainException($batch->breed_id ? "Link a semen stock item to {$batch->breed->name} first." : "Set the boar's breed before releasing its semen.", 'semen_not_stocked');
        }

        $item->tracks_batches || throw new DomainException("{$item->name} must be tracked by batch.", 'semen_item_batches');

        return $item;
    }
}
