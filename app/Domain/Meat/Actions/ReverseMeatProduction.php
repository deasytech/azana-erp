<?php

namespace App\Domain\Meat\Actions;

use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\CarcassStatus;
use App\Enums\MeatProductionStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Undoes a production run entered in error: the meat leaves stock again and the carcasses go back to hanging. Refused once any of it has been sold or used. */
class ReverseMeatProduction
{
    public function __construct(private readonly ReverseInventoryTransaction $reverse) {}

    public function __invoke(MeatProductionBatch $batch, string $reason, ?User $actor = null): MeatProductionBatch
    {
        trim($reason) !== '' || throw new DomainException('A reason is required to reverse meat production.', 'reason_required');

        return DB::transaction(function () use ($batch, $reason, $actor) {
            $batch = MeatProductionBatch::lockForUpdate()->findOrFail($batch->id);
            $batch->status === MeatProductionStatus::Produced || throw new DomainException("{$batch->number} is {$batch->status->label()}.", 'batch_state');

            ($this->reverse)->group($batch->group_uuid, "Meat production {$batch->number} reversed: ".trim($reason), $actor);

            Carcass::where('meat_production_batch_id', $batch->id)->update(['status' => CarcassStatus::Hanging, 'meat_production_batch_id' => null]);
            $batch->update([
                'status' => MeatProductionStatus::Reversed, 'reversed_at' => now(),
                'reversed_by' => ($actor ?? Auth::user())?->getKey(), 'reverse_reason' => trim($reason),
            ]);

            return $batch;
        });
    }
}
