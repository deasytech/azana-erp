<?php

namespace App\Domain\Semen\Actions;

use App\Domain\Farm\Actions\GetItemPrice;
use App\Domain\Inventory\Models\InventoryItem;
use Carbon\CarbonInterface;

/** The price of a dose of a breed's semen from the active price lists (see GetItemPrice). */
class GetSemenPrice
{
    public function __construct(private readonly GetItemPrice $price) {}

    /** @return array{price_minor: int, currency: string, price_list: string}|null */
    public function __invoke(InventoryItem|int $item, ?CarbonInterface $on = null): ?array
    {
        return ($this->price)($item, $on);
    }
}
