<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Inventory\Models\InventoryItem;
use Carbon\CarbonInterface;

/**
 * The price of a dose of a breed's semen from the farm's active price lists (the one that took effect most recently
 * wins). Prices are configuration, entered on price list items linked to the breed's semen stock item.
 */
class GetSemenPrice
{
    /** @return array{price_minor: int, currency: string, price_list: string}|null */
    public function __invoke(InventoryItem|int $item, ?CarbonInterface $on = null): ?array
    {
        $day = ($on ?? now())->toDateString();

        $price = PriceListItem::with('priceList')
            ->where('inventory_item_id', $item instanceof InventoryItem ? $item->id : $item)
            ->whereHas('priceList', fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $day))
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day)))
            ->get()
            ->sortByDesc(fn (PriceListItem $i) => [$i->priceList->valid_from?->timestamp ?? 0, $i->id])
            ->first();

        return $price ? ['price_minor' => $price->unit_price_minor, 'currency' => $price->priceList->currency_code, 'price_list' => $price->priceList->code] : null;
    }
}
