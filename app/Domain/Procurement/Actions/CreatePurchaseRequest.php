<?php

namespace App\Domain\Procurement\Actions;

use App\Domain\Procurement\Concerns\CleansPurchaseLines;
use App\Domain\Procurement\Models\PurchaseRequest;
use App\Domain\System\Actions\NextNumber;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Raises a purchase request (a draft until it is submitted).
 * Each line: inventory_item_id, quantity, estimated_unit_cost_minor (optional), notes (optional).
 */
class CreatePurchaseRequest
{
    use CleansPurchaseLines;

    public function __construct(private readonly NextNumber $nextNumber) {}

    /** @param list<array<string, mixed>> $lines */
    public function __invoke(array $lines, ?CarbonInterface $neededBy = null, ?string $notes = null, ?User $actor = null): PurchaseRequest
    {
        $lines = $this->cleanLines($lines);

        return DB::transaction(function () use ($lines, $neededBy, $notes, $actor) {
            $request = PurchaseRequest::create([
                'number' => sprintf('PR-%06d', ($this->nextNumber)('purchase_request')),
                'needed_by' => $neededBy,
                'notes' => $notes,
                'requested_by' => ($actor ?? Auth::user())?->getKey(),
            ]);

            foreach ($lines as $line) {
                $request->lines()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $line['quantity'],
                    'estimated_unit_cost_minor' => $line['estimated_unit_cost_minor'] ?? null,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $request->load('lines');
        });
    }
}
