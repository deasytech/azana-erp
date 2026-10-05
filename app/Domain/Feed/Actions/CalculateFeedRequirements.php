<?php

namespace App\Domain\Feed\Actions;

use App\Domain\Feed\Concerns\ConvertsFeedQuantities;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;
use Illuminate\Support\Collection;

/** The raw materials needed to make a given amount of finished feed from a formula. */
class CalculateFeedRequirements
{
    use ConvertsFeedQuantities;

    /** @return Collection<int, array{item: InventoryItem, kg: string, quantity: string}> quantity is in the item's own unit */
    public function __invoke(FeedFormula $formula, string $outputKg): Collection
    {
        if (! preg_match('/^\d{1,9}(\.\d{1,3})?$/', $outputKg) || bccomp($outputKg, '0', 3) <= 0) {
            throw new DomainException('The output must be a positive number of kilograms with at most 3 decimals.', 'output_kg');
        }

        $input = $this->inputKg($outputKg, (string) $formula->process_loss_percent);

        return $formula->items()->with('item.unit')->get()->map(function ($line) use ($input) {
            $kg = bcdiv(bcmul($input, (string) $line->inclusion_percent, 8), '100', 8);

            return ['item' => $line->item, 'kg' => $kg, 'quantity' => $this->kgToItemUnit($kg, $line->item)];
        });
    }
}
