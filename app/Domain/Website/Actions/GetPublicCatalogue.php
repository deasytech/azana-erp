<?php

namespace App\Domain\Website\Actions;

use App\Domain\Farm\Actions\GetItemPrice;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Semen\Actions\GetSemenStock;
use App\Domain\Website\Models\Listing;
use App\Enums\ListingKind;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The offer shown to the public. Prices come from the farm's price lists (GetItemPrice) and availability from the stock ledger and
 * the semen release rules; the site holds neither. A visitor learns only whether something is available, never how much is in stock.
 */
class GetPublicCatalogue
{
    public function __construct(
        private readonly GetItemPrice $price,
        private readonly GetStockLevels $stock,
        private readonly GetSemenStock $semenStock,
    ) {}

    /**
     * @return Collection<int, array{listing: Listing, price: ?string, available: ?bool}> published listings, in display order
     */
    public function __invoke(?ListingKind $kind = null): Collection
    {
        $listings = Listing::with('item')
            ->where('is_published', true)
            ->when($kind, fn ($q) => $q->where('kind', $kind->value))
            ->orderBy('sort_order')->orderBy('title')
            ->get();

        $sellableBreeds = $listings->contains(fn (Listing $l) => $l->kind === ListingKind::Semen)
            ? ($this->semenStock)()->filter(fn (array $row) => $row['sellable'])->pluck('semen_batch.breed_id')->unique()->all()
            : [];

        return $listings->map(fn (Listing $listing) => [
            'listing' => $listing,
            'price' => $this->price($listing),
            'available' => $this->available($listing, $sellableBreeds),
        ]);
    }

    public function find(string $slug): ?array
    {
        return $this->__invoke()->first(fn (array $row) => $row['listing']->slug === $slug);
    }

    private function price(Listing $listing): ?string
    {
        if (! $listing->show_price || ! $listing->item) {
            return null;
        }

        $price = ($this->price)($listing->item);

        if (! $price) {
            return null;
        }

        $formatted = Money::ofMinor($price['price_minor'], $price['currency'])->format();

        return $listing->price_unit ? "{$formatted} {$listing->price_unit}" : $formatted;
    }

    /** @param list<int> $sellableBreeds */
    private function available(Listing $listing, array $sellableBreeds): ?bool
    {
        return match (true) {
            ! $listing->item => null,
            $listing->kind === ListingKind::Semen => $listing->item->breed_id !== null && in_array($listing->item->breed_id, $sellableBreeds, true),
            $listing->kind === ListingKind::Meat => bccomp($this->stock->total($listing->item->id), '0', 3) > 0,
            default => null,
        };
    }
}
