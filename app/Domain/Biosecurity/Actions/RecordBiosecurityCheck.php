<?php

namespace App\Domain\Biosecurity\Actions;

use App\Domain\Biosecurity\Models\BiosecurityCheck;
use App\Domain\Biosecurity\Models\BiosecurityChecklistItem;
use App\Domain\System\Exceptions\DomainException;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Records a completed biosecurity inspection; the checklist wording is snapshotted with each answer. */
class RecordBiosecurityCheck
{
    /** @param list<array{item_id: int, passed: bool, notes?: ?string}> $results */
    public function __invoke(CarbonInterface $checkedOn, array $results, ?int $productionUnitId = null, ?string $notes = null, ?User $actor = null): BiosecurityCheck
    {
        if ($results === []) {
            throw new DomainException('A check needs at least one checklist item.', 'check_empty');
        }

        if ($checkedOn->gt(now()->addMinutes(5))) {
            throw new DomainException('A check cannot be dated in the future.', 'check_future');
        }

        return DB::transaction(function () use ($checkedOn, $results, $productionUnitId, $notes, $actor) {
            $items = BiosecurityChecklistItem::where('is_active', true)->whereIn('id', collect($results)->pluck('item_id'))->get()->keyBy('id');

            if ($items->count() !== count($results)) {
                throw new DomainException('Every answer must be for an active checklist item, once each.', 'check_items');
            }

            $check = BiosecurityCheck::create([
                'production_unit_id' => $productionUnitId,
                'checked_on' => $checkedOn,
                'performed_by' => ($actor ?? Auth::user())?->getKey(),
                'items_total' => count($results),
                'items_passed' => collect($results)->where('passed', true)->count(),
                'notes' => $notes,
            ]);

            foreach ($results as $answer) {
                $check->items()->create([
                    'checklist_item_id' => $answer['item_id'],
                    'description' => $items[$answer['item_id']]->description,
                    'passed' => (bool) $answer['passed'],
                    'notes' => $answer['notes'] ?? null,
                ]);
            }

            return $check;
        });
    }
}
