<?php

namespace App\Domain\Feed\Concerns;

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\InventoryItem;
use App\Support\Ratio;

/** Feed maths is done in kilograms; stock items may be counted in other units (tonnes, bags). */
trait ConvertsFeedQuantities
{
    /** A kilogram amount as a quantity in the item's own unit (rounded to 3 decimals, half up). */
    private function kgToItemUnit(string $kg, InventoryItem $item): string
    {
        $unit = $item->unit ?? UnitOfMeasure::findOrFail($item->unit_id);

        return (string) Ratio::average(UnitOfMeasure::firstWhere('code', 'KG')->convertTo($kg, $unit), '1', 3);
    }

    /** A quantity in the item's own unit as kilograms. */
    private function itemUnitToKg(string $quantity, InventoryItem $item): string
    {
        $unit = $item->unit ?? UnitOfMeasure::findOrFail($item->unit_id);

        return $unit->convertTo($quantity, UnitOfMeasure::firstWhere('code', 'KG'));
    }

    /** Kilograms of raw material needed for $outputKg of finished feed, allowing for the expected process loss. */
    private function inputKg(string $outputKg, string $lossPercent): string
    {
        return bcdiv($outputKg, bcsub('1', bcdiv($lossPercent, '100', 8), 8), 8);
    }
}
