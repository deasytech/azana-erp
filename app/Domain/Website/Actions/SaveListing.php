<?php

namespace App\Domain\Website\Actions;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Models\Listing;
use App\Enums\InventoryCategory;
use App\Enums\ListingKind;
use Illuminate\Support\Str;

/**
 * Creates or updates something the public site offers. A listing may point at a stock item, and then its price and availability are read
 * from the price lists and the stock ledger, never typed here. Semen listings point at semen items, meat listings at meat items.
 */
class SaveListing
{
    /** @param array{kind: string, title: string, summary: string, description?: ?string, slug?: ?string, inventory_item_id?: ?int, price_unit?: ?string, show_price?: bool, sort_order?: ?int, is_published?: bool} $data */
    public function __invoke(array $data, ?Listing $listing = null): Listing
    {
        $kind = ListingKind::tryFrom((string) ($data['kind'] ?? '')) ?? throw new DomainException('Choose what kind of listing this is.', 'listing_kind');
        $title = trim((string) ($data['title'] ?? ''));
        $title !== '' || throw new DomainException('Give the listing a title.', 'listing_title');
        trim((string) ($data['summary'] ?? '')) !== '' || throw new DomainException('Write a one-line summary.', 'listing_summary');

        $slug = Str::slug((string) ($data['slug'] ?? '') ?: $title);
        $slug !== '' || throw new DomainException('The address needs letters or numbers.', 'listing_slug');
        Listing::where('slug', $slug)->when($listing, fn ($q) => $q->whereKeyNot($listing->id))->doesntExist() || throw new DomainException("Another listing already uses the address \"{$slug}\".", 'listing_slug_taken');

        $item = filled($data['inventory_item_id'] ?? null) ? InventoryItem::findOrFail($data['inventory_item_id']) : null;

        if ($item) {
            $expected = match ($kind) {
                ListingKind::Semen => InventoryCategory::Semen,
                ListingKind::Meat => InventoryCategory::Meat,
                default => throw new DomainException('Only semen and meat listings can be linked to a stock item.', 'listing_item_kind'),
            };
            $item->category === $expected || throw new DomainException("A {$kind->label()} listing needs a {$expected->label()} stock item.", 'listing_item_category');
        }

        ! ($data['show_price'] ?? false) || $item || throw new DomainException('To show a price, link a stock item: the price comes from the price lists.', 'listing_price_item');

        $fields = [
            'kind' => $kind, 'slug' => $slug, 'title' => mb_substr($title, 0, 255), 'summary' => mb_substr(trim($data['summary']), 0, 300),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'inventory_item_id' => $item?->id, 'price_unit' => filled($data['price_unit'] ?? null) ? mb_substr(trim($data['price_unit']), 0, 30) : null,
            'show_price' => (bool) ($data['show_price'] ?? false), 'sort_order' => (int) ($data['sort_order'] ?? 100), 'is_published' => (bool) ($data['is_published'] ?? false),
        ];

        if ($listing) {
            $listing->update($fields);

            return $listing;
        }

        return Listing::create($fields);
    }
}
