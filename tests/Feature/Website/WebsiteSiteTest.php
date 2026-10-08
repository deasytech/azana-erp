<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Website\Models\Enquiry;
use App\Livewire\Public\EnquiryForm;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    RateLimiter::clear('enquiry-minute:127.0.0.1');
    RateLimiter::clear('enquiry-day:127.0.0.1');
});

describe('pages', function () {
    it('serves every public page without signing in', function () {
        semenListing();

        foreach (['/', '/about', '/operations', '/sustainability', '/products', '/contact', '/pigs', '/semen', '/meat', '/products/duroc-semen', '/sitemap.xml', '/robots.txt'] as $path) {
            $this->get($path)->assertOk();
        }
    });

    it('shows the farm, its contact details and a priced listing from the ERP records', function () {
        semenListing();
        releasedSemen();
        app(ResolveSettings::class)->set('website.about', "We raise pigs.\n\nOn our own feed.");

        $this->get('/')->assertSee('Quality pigs, boar semen and fresh pork.')->assertSee('We raise pigs.');
        $this->get('/products')->assertSee('Duroc semen')->assertSee('NGN 12,000.00 per dose')->assertSee('Available');
        $this->get('/about')->assertSee('On our own feed.');
    });

    it('never shows how much stock there is, or an unpublished listing', function () {
        semenListing(['title' => 'Hidden semen', 'slug' => 'hidden', 'is_published' => false]);
        releasedSemen();

        $this->get('/semen')->assertDontSee('Hidden semen')->assertDontSee('24 doses')->assertDontSee('in stock');
        $this->get('/products/hidden')->assertNotFound();
        $this->get('/products/unknown')->assertNotFound();
        $this->get('/pigs/extra')->assertNotFound();
    });

    it('presents the company with the logo, the navigation and the home sections', function () {
        $html = $this->get('/')->assertOk()->assertSee('images/branding/logo.jpeg', false)->assertSee('Explore Our Farm')->assertSee('Contact Us')
            ->assertSee('Technology behind better farming')->assertSee('Why Azana Farms')->assertSee('Sustainability')->assertSee('A subsidiary of')
            ->assertSee('class="burger"', false)->assertSee('aria-controls="drawer"', false)->getContent();

        foreach (['site.about', 'site.operations', 'site.products', 'site.sustainability', 'site.contact'] as $route) {
            expect($html)->toContain('href="'.route($route).'"');
        }
    });

    it('shows a plain panel, not a broken or stock picture, until the farm adds photographs', function () {
        $this->get('/')->assertSee('class="ph"', false)->assertDontSee('<img src="'.asset('images/site/'), false);
    });

    it('states no figures, prices or contact details the farm has not entered', function () {
        $this->get('/')->assertDontSee('Opening hours')->assertDontSee('tel:')->assertDontSee('mailto:')->assertDontSee('wa.me');
    });

    it('has what search engines and screen readers need', function () {
        semenListing();

        $this->get('/products/duroc-semen')
            ->assertSee('<title>Duroc semen | ', false)->assertSee('<meta name="description"', false)->assertSee('rel="canonical"', false)
            ->assertSee('lang="en"', false)->assertSee('class="skip"', false)->assertSee('<main id="main"', false)->assertSee('application/ld+json', false)
            ->assertSee('<h1>Duroc semen</h1>', false);
        $this->get('/sitemap.xml')->assertSee(route('site.product', 'duroc-semen'), false)->assertSee(route('site.contact'), false)->assertHeader('Content-Type', 'application/xml');
        $this->get('/robots.txt')->assertSee('Disallow: /admin')->assertSee('Sitemap: '.route('site.sitemap'));
    });

    it('does not link to the ERP and does not use its styles', function () {
        $html = $this->get('/')->getContent();

        expect($html)->not->toContain('/admin')->not->toContain('filament');
    });

    it('keeps the ERP behind its sign-in', function () {
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
        $this->get('/admin/website-enquiries')->assertRedirect();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('answers only on the website host when one is set', function () {
        config(['website.host' => 'www.azanafarms.com']);
        app('router')->setRoutes(new RouteCollection);
        require base_path('routes/web.php');
        app('router')->getRoutes()->refreshNameLookups();

        $this->get('http://www.azanafarms.com/about')->assertOk();
        $this->get('http://erp.azanafarms.com/about')->assertNotFound();
    });

    it('shows the contact details instead of the form when enquiries are off', function () {
        app(ResolveSettings::class)->set('website.enquiries_enabled', false);

        $this->get('/contact')->assertSee('not taking enquiries')->assertDontSee('Send enquiry');
    });
});

describe('enquiry form', function () {
    it('sends an enquiry and shows the reference', function () {
        Livewire::test(EnquiryForm::class, ['kind' => 'pigs'])
            ->set('name', 'Ada Obi')->set('email', 'ada@example.com')->set('message', 'I need twenty weaners next month.')
            ->call('submit')->assertHasNoErrors()->assertSet('sent', 'ENQ-000001')->assertSee('ENQ-000001')->assertSet('name', '');

        expect(Enquiry::sole())->kind->value->toBe('pigs')->name->toBe('Ada Obi');
    });

    it('pre-selects the product being asked about', function () {
        $listing = semenListing();

        Livewire::test(EnquiryForm::class, ['listingId' => $listing->id])->assertSet('kind', 'semen')->assertSet('listingId', $listing->id)
            ->set('name', 'Ada')->set('phone', '0803 000 0000')->set('message', 'How soon can I have 50 doses?')->call('submit');

        expect(Enquiry::sole()->listing_id)->toBe($listing->id);
        Livewire::test(EnquiryForm::class, ['listingId' => 9999])->assertSet('listingId', null)->assertSet('kind', 'general');
    });

    it('explains what is missing, field by field', function () {
        Livewire::test(EnquiryForm::class)->call('submit')
            ->assertHasErrors(['name' => 'required', 'message' => 'required', 'email' => 'required_without', 'phone' => 'required_without']);
        Livewire::test(EnquiryForm::class)->set('name', 'A')->set('email', 'nope')->set('message', 'short')->set('phone', 'abc')->call('submit')
            ->assertHasErrors(['email', 'message' => 'min', 'phone' => 'regex']);

        expect(Enquiry::count())->toBe(0);
    });

    it('keeps nothing from a script that fills the hidden field', function () {
        Livewire::test(EnquiryForm::class)->set('name', 'Bot')->set('email', 'bot@example.com')->set('message', 'Buy cheap watches today!!')->set('website', 'http://spam.example')
            ->call('submit')->assertSet('sent', 'ENQ');

        expect(Enquiry::count())->toBe(0);
    });

    it('slows a flood down', function () {
        foreach (range(1, 3) as $i) {
            Livewire::test(EnquiryForm::class)->set('name', "Ada {$i}")->set('email', 'ada@example.com')->set('message', "Message number {$i} about pigs")->call('submit')->assertHasNoErrors();
        }

        Livewire::test(EnquiryForm::class)->set('name', 'Ada 4')->set('email', 'ada@example.com')->set('message', 'Message number 4 about pigs')->call('submit')
            ->assertHasErrors('message')->assertSet('sent', null);
        expect(Enquiry::count())->toBe(3);
    });

    it('tells the visitor when enquiries are closed instead of losing the message', function () {
        app(ResolveSettings::class)->set('website.enquiries_enabled', false);

        Livewire::test(EnquiryForm::class)->set('name', 'Ada')->set('email', 'ada@example.com')->set('message', 'I need twenty weaners.')->call('submit')
            ->assertHasErrors('message')->assertSee('not being taken');
    });
});
