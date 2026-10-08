<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\Sales\Models\StockReservation;
use App\Domain\System\Exceptions\DomainException;
use App\Domain\Website\Actions\ConvertEnquiryToCustomer;
use App\Domain\Website\Actions\GetPublicCatalogue;
use App\Domain\Website\Actions\SaveListing;
use App\Domain\Website\Actions\SubmitEnquiry;
use App\Domain\Website\Actions\UpdateEnquiryStatus;
use App\Domain\Website\Models\Enquiry;
use App\Domain\Website\Models\Listing;
use App\Domain\Website\Notifications\EnquiryReceived;
use App\Enums\EnquiryStatus;
use App\Enums\LookupCategory;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

/** A published listing for Duroc semen priced from the price list at 12,000.00 a dose. */
function semenListing(array $over = []): Listing
{
    $item = InventoryItem::firstWhere('code', 'SEMEN-DUR');
    $list = PriceList::create(['farm_id' => Farm::first()->id, 'category_id' => lookup(LookupCategory::PriceCategory, 'semen'), 'code' => 'SEM', 'name' => 'Semen', 'currency_code' => 'NGN', 'is_active' => true]);
    PriceListItem::create(['price_list_id' => $list->id, 'code' => 'DUR', 'description' => 'Duroc', 'unit_id' => UnitOfMeasure::firstWhere('code', 'DOSE')->id, 'unit_price_minor' => 1200000, 'inventory_item_id' => $item->id]);

    return app(SaveListing::class)($over + ['kind' => 'semen', 'title' => 'Duroc semen', 'summary' => 'Tested doses.', 'inventory_item_id' => $item->id, 'price_unit' => 'per dose', 'show_price' => true, 'is_published' => true]);
}

function enquiryData(array $over = []): array
{
    return $over + ['kind' => 'pigs', 'name' => 'Ada Obi', 'email' => 'ada@example.com', 'message' => 'I need twenty weaners next month.'];
}

describe('listings', function () {
    it('quotes the price from the price list, never from the listing', function () {
        semenListing();

        $row = app(GetPublicCatalogue::class)()->first();
        expect($row['price'])->toBe('NGN 12,000.00 per dose');

        PriceListItem::query()->update(['unit_price_minor' => 1500000]);
        expect(app(GetPublicCatalogue::class)()->first()['price'])->toBe('NGN 15,000.00 per dose');
    });

    it('offers semen only while a released, in-date batch is in stock', function () {
        semenListing();
        expect(app(GetPublicCatalogue::class)()->first()['available'])->toBeFalse();

        releasedSemen();
        expect(app(GetPublicCatalogue::class)()->first()['available'])->toBeTrue();
    });

    it('shows nothing unpublished and hides the price unless asked', function () {
        semenListing(['is_published' => false]);
        expect(app(GetPublicCatalogue::class)())->toHaveCount(0);

        Listing::query()->update(['is_published' => true, 'show_price' => false]);
        expect(app(GetPublicCatalogue::class)()->first()['price'])->toBeNull();
    });

    it('checks what a listing links to', function () {
        $semen = InventoryItem::firstWhere('code', 'SEMEN-DUR');
        $save = fn (array $d) => app(SaveListing::class)($d + ['kind' => 'pigs', 'title' => 'Weaners', 'summary' => 'Healthy weaners.']);

        expect(fn () => $save(['inventory_item_id' => $semen->id]))->toThrow(DomainException::class, 'Only semen and meat');
        expect(fn () => $save(['kind' => 'meat', 'inventory_item_id' => $semen->id]))->toThrow(DomainException::class, 'needs a Meat stock item');
        expect(fn () => $save(['kind' => 'semen', 'show_price' => true]))->toThrow(DomainException::class, 'link a stock item');
        expect(fn () => $save(['title' => '']))->toThrow(DomainException::class, 'title');

        $first = $save([]);
        expect($first->slug)->toBe('weaners');
        expect(fn () => $save([]))->toThrow(DomainException::class, 'already uses');
        expect($save(['slug' => 'weaners-2'])->slug)->toBe('weaners-2');
        expect(app(SaveListing::class)(['summary' => 'Updated.'] + $first->attributesToArray(), $first)->summary)->toBe('Updated.');
    });
});

describe('enquiries', function () {
    it('records an enquiry, numbers it and tells the people who run the website', function () {
        Notification::fake();
        $manager = userWithRole('General Manager');
        $worker = farmWorker();

        $enquiry = app(SubmitEnquiry::class)(enquiryData(['phone' => '0803 000 0000', 'quantity' => '20 weaners']));

        expect($enquiry->number)->toBe('ENQ-000001')->and($enquiry->status)->toBe(EnquiryStatus::New)->and($enquiry->quantity)->toBe('20 weaners');
        Notification::assertSentTo($manager, EnquiryReceived::class);
        Notification::assertNotSentTo($worker, EnquiryReceived::class);
    });

    it('does not reserve stock or create an order, a customer or a price', function () {
        releasedSemen();
        app(SubmitEnquiry::class)(enquiryData(['kind' => 'semen']));

        expect(Customer::count())->toBe(0)->and(SalesOrder::count())->toBe(0)->and(StockReservation::count())->toBe(0);
    });

    it('treats the same words sent twice in a few minutes as one enquiry', function () {
        Notification::fake();
        $a = app(SubmitEnquiry::class)(enquiryData());
        $b = app(SubmitEnquiry::class)(enquiryData());

        expect($b->id)->toBe($a->id)->and(Enquiry::count())->toBe(1);
    });

    it('needs a name, a way to reply, a real message and a listed product', function () {
        $send = fn (array $d) => fn () => app(SubmitEnquiry::class)(enquiryData($d));

        expect($send(['name' => ' ']))->toThrow(DomainException::class, 'name');
        expect($send(['email' => null, 'phone' => null]))->toThrow(DomainException::class, 'phone number or an e-mail');
        expect($send(['email' => 'not-an-email']))->toThrow(DomainException::class, 'not valid');
        expect($send(['message' => 'Hi']))->toThrow(DomainException::class, 'more');
        expect($send(['kind' => 'cats']))->toThrow(DomainException::class, 'about');
        expect($send(['listing_id' => semenListing(['is_published' => false])->id]))->toThrow(DomainException::class, 'no longer listed');
        expect(Enquiry::count())->toBe(0);
    });

    it('stops taking enquiries when the setting is off', function () {
        app(ResolveSettings::class)->set('website.enquiries_enabled', false);

        expect(fn () => app(SubmitEnquiry::class)(enquiryData()))->toThrow(DomainException::class, 'not being taken');
    });

    it('keeps no readable address, only a hash', function () {
        $enquiry = app(SubmitEnquiry::class)(enquiryData(['source' => '203.0.113.9']));

        expect($enquiry->source_ip_hash)->toHaveLength(64)->not->toContain('203.0.113.9');
    });
});

describe('handling', function () {
    it('moves an enquiry along and lets a finished one be reopened', function () {
        $enquiry = app(SubmitEnquiry::class)(enquiryData());
        $staff = userWithRole('Sales Officer');
        $update = app(UpdateEnquiryStatus::class);

        expect($update($enquiry, EnquiryStatus::Contacted, 'Called, will visit', $staff))->status->toBe(EnquiryStatus::Contacted);
        $update($enquiry, EnquiryStatus::Closed, null, $staff);
        $closed = $update($enquiry, EnquiryStatus::New, null, $staff);

        expect($closed->staff_note)->toBe('Called, will visit')->and($closed->handled_by)->toBe($staff->id);
        expect(fn () => $update($enquiry, EnquiryStatus::Converted))->toThrow(DomainException::class, 'Make customer');
    });

    it('makes a customer from an enquiry once, reusing one with the same e-mail', function () {
        $existing = customer(['email' => 'ada@example.com']);
        $enquiry = app(SubmitEnquiry::class)(enquiryData(['email' => 'ADA@example.com']));
        $type = lookup(LookupCategory::CustomerType, 'farmer');

        $customer = app(ConvertEnquiryToCustomer::class)($enquiry, $type);

        expect($customer->id)->toBe($existing->id)->and($enquiry->refresh()->status)->toBe(EnquiryStatus::Converted)->and(Customer::count())->toBe(1);
        expect(fn () => app(ConvertEnquiryToCustomer::class)($enquiry, $type))->toThrow(DomainException::class, 'already belongs');
        expect(fn () => app(UpdateEnquiryStatus::class)($enquiry, EnquiryStatus::Closed))->toThrow(DomainException::class, 'cannot be changed');
    });

    it('creates a new cash customer with the business as its name', function () {
        $enquiry = app(SubmitEnquiry::class)(enquiryData(['organisation' => 'Green Acres Farm', 'phone' => '08030000000']));

        $customer = app(ConvertEnquiryToCustomer::class)($enquiry, lookup(LookupCategory::CustomerType, 'farmer'));

        expect($customer->name)->toBe('Green Acres Farm')->and($customer->contact_name)->toBe('Ada Obi')->and($customer->credit_status->value)->toBe('none');
    });
});
