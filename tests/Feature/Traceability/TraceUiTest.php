<?php

use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Sales\Models\SalesOrder;
use App\Filament\Pages\TraceExplorer;
use App\Filament\Resources\Animals\Pages\ViewAnimal;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\PurchasesRelationManager;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\LinesRelationManager as InvoiceLines;
use App\Filament\Resources\MeatProductionBatches\Pages\ViewMeatProductionBatch;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SemenBatches\Pages\ViewSemenBatch;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('shows the whole story of a meat batch from supplier to customer', function () {
    $this->actingAs(owner());
    $c = traceChain();
    $this->get(TraceExplorer::getUrl())->assertOk();

    Livewire::test(TraceExplorer::class)->assertSee('Enter a number')
        ->set('reference', strtolower($c['meat']->number))
        ->assertSee('Tracing')->assertSee($c['meat']->number)->assertSee('Supplier SUP1')->assertSee('Item PREMIX, batch PX-1')->assertSee($c['semen']->number)
        ->assertSee($c['litter']->litter_number)->assertSee($c['carcass']->number)->assertSee('Pen TRC1')->assertSee('City Meats')->assertSee($c['meatInvoice']->number)
        ->assertSee('before')->assertSee('after')
        ->assertDontSee('Stud Farm');   // the semen buyer is not part of the meat's story
});

it('takes the subject and number from the address, and traces an animal and a semen batch', function () {
    $this->actingAs(owner());
    $c = traceChain();

    Livewire::withQueryParams(['subject' => 'animal', 'reference' => $c['pig']->animal_number])->test(TraceExplorer::class)
        ->assertSee($c['meat']->number)->assertSee('City Meats')->assertSee('Supplier SUP1');

    Livewire::withQueryParams(['subject' => 'semen_batch', 'reference' => $c['semen']->number])->test(TraceExplorer::class)
        ->assertSee('Stud Farm')->assertSee($c['sow']->animal_number)->assertDontSee('City Meats');
});

it('says so when nothing is recorded, and survives a tampered subject', function () {
    $this->actingAs(owner());

    Livewire::test(TraceExplorer::class)->set('reference', 'NOPE-1')->assertSee('Nothing is recorded under NOPE-1.')
        ->set('subject', 'nonsense')->assertSee('Choose what to trace.')->assertDontSee('Nothing is recorded');
});

it('shows people who may not see sales the trace without customers and invoices', function () {
    $c = traceChain();

    $this->actingAs(userWithRole('Slaughter Manager'));
    Livewire::test(TraceExplorer::class)->set('reference', $c['meat']->number)
        ->assertSee('Supplier SUP1')->assertSee($c['semen']->number)->assertSee($c['carcass']->number)
        ->assertDontSee('City Meats')->assertDontSee($c['meatInvoice']->number);

    $this->actingAs(userWithRole('Accountant'));
    Livewire::test(TraceExplorer::class)->set('reference', $c['meat']->number)->assertSee('City Meats');
});

it('keeps the explorer away from people who may not see animals and slaughter', function () {
    $this->actingAs(farmWorker());
    $this->get(TraceExplorer::getUrl())->assertForbidden();

    $this->actingAs(userWithRole('Store Officer'));
    $this->get(TraceExplorer::getUrl())->assertForbidden();
});

it('offers "Trace this product" on a meat batch, a pig and a semen batch', function () {
    $this->actingAs(owner());
    $c = traceChain();

    Livewire::test(ViewMeatProductionBatch::class, ['record' => $c['meat']->getRouteKey()])->assertActionVisible('trace')
        ->assertActionHasUrl('trace', TraceExplorer::urlFor('meat_batch', $c['meat']->number));
    Livewire::test(ViewAnimal::class, ['record' => $c['pig']->getRouteKey()])->assertActionVisible('trace')
        ->assertActionHasUrl('trace', TraceExplorer::urlFor('animal', $c['pig']->animal_number));
    Livewire::test(ViewSemenBatch::class, ['record' => $c['semen']->getRouteKey()])->assertActionVisible('trace')
        ->assertActionHasUrl('trace', TraceExplorer::urlFor('semen_batch', $c['semen']->number));

    $this->actingAs(farmWorker());
    Livewire::test(ViewAnimal::class, ['record' => $c['pig']->getRouteKey()])->assertActionHidden('trace');
});

it('sells meat from the order form, picks the lots and shows the picking list', function () {
    $this->actingAs(owner());
    twoLotsOfLeg();
    $customer = creditCustomer();

    Livewire::test(CreateSalesOrder::class)
        ->fillForm(['customer_id' => $customer->id, 'ordered_on' => now()->toDateString(), 'lines' => [[
            'kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => 30, 'unit_price_minor' => '2500.00', 'discount_percent' => 0,
        ]]])
        ->call('create')->assertHasNoFormErrors();

    $order = SalesOrder::sole();
    expect($order->lines)->toHaveCount(2)->and($order->total_minor)->toBe(7500000);

    Livewire::test(ViewSalesOrder::class, ['record' => $order->getRouteKey()])->assertDontSee('Picking list')
        ->callAction('confirm')->assertNotified('Order confirmed and stock reserved')
        ->assertSee('Picking list')->assertSee('Cold room 1')->assertSee('20.000 kg')->assertSee('10.000 kg')
        ->callAction('dispatch', ['dispatched_on' => now()->toDateString()])->assertNotified('Order dispatched and invoiced');

    $invoice = $order->fresh()->invoice;
    Livewire::test(InvoiceLines::class, ['ownerRecord' => $invoice, 'pageClass' => ViewInvoice::class])->assertSee('Meat batch IPA-MT-');
    Livewire::test(PurchasesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])->assertSee('Leg')->assertSee($invoice->number)->assertSee('IPA-MT-');
});

it('shows a meat order that cannot be filled as a notification', function () {
    $this->actingAs(owner());
    makeMeat();

    Livewire::test(CreateSalesOrder::class)
        ->fillForm(['customer_id' => creditCustomer()->id, 'ordered_on' => now()->toDateString(), 'lines' => [[
            'kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => 99, 'unit_price_minor' => '2500.00', 'discount_percent' => 0,
        ]]])
        ->call('create')->assertNotified('Not saved');

    expect(SalesOrder::count())->toBe(0);
});
