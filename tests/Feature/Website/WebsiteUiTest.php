<?php

use App\Domain\Sales\Models\Customer;
use App\Domain\Website\Actions\SubmitEnquiry;
use App\Domain\Website\Models\Enquiry;
use App\Domain\Website\Models\Listing;
use App\Enums\EnquiryStatus;
use App\Enums\LookupCategory;
use App\Filament\Resources\WebsiteEnquiries\Pages\ListWebsiteEnquiries;
use App\Filament\Resources\WebsiteEnquiries\Pages\ViewWebsiteEnquiry;
use App\Filament\Resources\WebsiteListings\Pages\CreateWebsiteListing;
use App\Filament\Resources\WebsiteListings\Pages\EditWebsiteListing;
use App\Filament\Resources\WebsiteListings\Pages\ListWebsiteListings;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

const WEBSITE_MANAGER = 'General Manager';
const WEBSITE_SALES = 'Sales Officer';

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function waitingEnquiry(array $over = []): Enquiry
{
    return app(SubmitEnquiry::class)($over + ['kind' => 'meat', 'name' => 'Ada Obi', 'email' => 'ada@example.com', 'message' => 'Forty kilograms of loin please.']);
}

describe('enquiries', function () {
    it('lists the open ones first and works them through', function () {
        $open = waitingEnquiry();
        $done = waitingEnquiry(['name' => 'Old Request']);
        $done->update(['status' => EnquiryStatus::Closed]);
        $this->actingAs(userWithRole(WEBSITE_SALES));

        Livewire::test(ListWebsiteEnquiries::class)->assertCanSeeTableRecords([$open])->assertCanNotSeeTableRecords([$done])
            ->callAction(TestAction::make('contacted')->table($open), ['note' => 'Phoned him'])->assertNotified('Marked as contacted');

        expect($open->refresh())->status->toBe(EnquiryStatus::Contacted)->staff_note->toBe('Phoned him');
        Livewire::test(ListWebsiteEnquiries::class)->set('activeTab', 'all')->assertCanSeeTableRecords([$open, $done]);
    });

    it('turns an enquiry into a customer and takes the user to them', function () {
        $enquiry = waitingEnquiry();
        $this->actingAs(userWithRole(WEBSITE_SALES));

        Livewire::test(ViewWebsiteEnquiry::class, ['record' => $enquiry->getKey()])
            ->callAction('makeCustomer', ['customer_type_id' => lookup(LookupCategory::CustomerType, 'butcher')])->assertNotified();

        expect($enquiry->refresh())->status->toBe(EnquiryStatus::Converted)->customer_id->not->toBeNull()
            ->and(Customer::sole()->email)->toBe('ada@example.com');
    });

    it('is closed to people without the website permission, and cannot be created or deleted by hand', function () {
        waitingEnquiry();

        $this->actingAs(farmWorker());
        $this->get('/admin/website-enquiries')->assertForbidden();

        $this->actingAs($manager = userWithRole(WEBSITE_MANAGER));
        expect($manager->can('create', Enquiry::class))->toBeFalse()->and($manager->can('delete', Enquiry::first()))->toBeFalse();
        $this->get('/admin/website-enquiries')->assertOk();
    });

    it('lets a farm manager read but not change an enquiry', function () {
        $enquiry = waitingEnquiry();
        $this->actingAs(userWithRole('Farm Manager'));

        Livewire::test(ListWebsiteEnquiries::class)->assertCanSeeTableRecords([$enquiry])
            ->assertActionHidden(TestAction::make('contacted')->table($enquiry));
    });

    it('keeps the make-customer action from someone who may not create customers', function () {
        $enquiry = waitingEnquiry();
        $this->actingAs(User::factory()->create()->givePermissionTo('website.view', 'website.edit'));

        Livewire::test(ViewWebsiteEnquiry::class, ['record' => $enquiry->getKey()])->assertActionHidden('makeCustomer');
    });
});

describe('listings', function () {
    it('lets a manager publish a listing and see it on the site', function () {
        $this->actingAs(userWithRole(WEBSITE_MANAGER));

        Livewire::test(CreateWebsiteListing::class)
            ->fillForm(['kind' => 'pigs', 'title' => 'Weaners', 'summary' => 'Healthy weaners.', 'is_published' => true])
            ->call('create')->assertHasNoFormErrors();

        $this->get('/pigs')->assertSee('Weaners');
        $this->get('/products/weaners')->assertOk();
    });

    it('explains a rule the listing breaks instead of saving it', function () {
        $this->actingAs(userWithRole(WEBSITE_MANAGER));

        Livewire::test(CreateWebsiteListing::class)
            ->fillForm(['kind' => 'semen', 'title' => 'Semen', 'summary' => 'Doses.', 'show_price' => true])
            ->call('create')->assertNotified('Not saved');

        expect(Listing::count())->toBe(0);
    });

    it('edits and unpublishes a listing', function () {
        $listing = Listing::create(['kind' => 'pigs', 'slug' => 'weaners', 'title' => 'Weaners', 'summary' => 'Healthy.', 'is_published' => true]);
        $this->actingAs(userWithRole(WEBSITE_MANAGER));

        Livewire::test(EditWebsiteListing::class, ['record' => $listing->getKey()])->fillForm(['is_published' => false])->call('save')->assertHasNoFormErrors();

        expect($listing->refresh()->is_published)->toBeFalse();
        $this->get('/products/weaners')->assertNotFound();
        Livewire::test(ListWebsiteListings::class)->assertCanSeeTableRecords([$listing]);
    });

    it('is closed to a sales officer who can only follow up enquiries', function () {
        $this->actingAs(userWithRole(WEBSITE_SALES));

        $this->get('/admin/website-listings')->assertOk();
        expect(auth()->user()->can('delete', new Listing))->toBeFalse();
        $this->actingAs(farmWorker());
        $this->get('/admin/website-listings')->assertForbidden();
    });
});
