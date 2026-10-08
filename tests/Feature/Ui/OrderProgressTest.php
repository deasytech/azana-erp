<?php

use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Domain\Procurement\Actions\DecidePurchaseRequest;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\PurchaseRequests\Pages\ViewPurchaseRequest;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('draws the purchase order journey from its status', function () {
    $this->actingAs(owner());
    $order = purchaseOrder(approve: false);
    $page = fn () => Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()]);

    $page()->assertSee('Progress')->assertSee('Drafted')->assertSee('Invoiced');

    app(DecidePurchaseOrder::class)->submit($order);
    $page()->assertSee('Waiting for approval');

    app(DecidePurchaseOrder::class)->reject($order->refresh(), userWithRole('Farm Manager'), 'Too dear');
    $page()->assertSee('Rejected')->assertDontSee('Waiting for approval');
});

it('draws the journey of a purchase request and a planned feed order', function () {
    $manager = userWithRole('Farm Manager');
    $this->actingAs($manager);

    $request = approvedRequest(userWithRole('Store Officer'), $manager);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->getRouteKey()])
        ->assertSee('Progress')->assertSee('Submitted')->assertSee('Ordered');
});

it('keeps the stages reached when a purchase order is cancelled after approval', function () {
    $this->actingAs(userWithRole('Store Officer'));
    $order = purchaseOrder(approve: true);
    app(DecidePurchaseOrder::class)->cancel($order);

    $this->actingAs(owner());
    Livewire::test(ViewPurchaseOrder::class, ['record' => $order->getRouteKey()])->assertSeeInOrder(['Drafted', 'Approved', 'Cancelled']);
});

it('keeps the stages reached when a purchase request is cancelled after approval', function () {
    $manager = userWithRole('Farm Manager');
    $request = approvedRequest(userWithRole('Store Officer'), $manager);
    app(DecidePurchaseRequest::class)->cancel($request);

    $this->actingAs($manager);
    Livewire::test(ViewPurchaseRequest::class, ['record' => $request->getRouteKey()])->assertSeeInOrder(['Drafted', 'Submitted', 'Approved', 'Cancelled']);
});
