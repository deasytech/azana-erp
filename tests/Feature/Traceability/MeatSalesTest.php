<?php

use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Meat\Actions\ReverseMeatProduction;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Sales\Actions\CancelSalesOrder;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Sales\Actions\DispatchSalesOrder;
use App\Domain\Sales\Actions\GetPickingList;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\SalesOrder;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType as T;
use App\Enums\LookupCategory;
use App\Enums\SalesOrderStatus as S;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    $this->manager = userWithRole('Farm Manager');
});

function legOrder(Customer $customer, string $kg, array $extra = []): SalesOrder
{
    return app(CreateSalesOrder::class)($customer, [[
        'kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => $kg, 'unit_price_minor' => 250000,
    ] + $extra], now());
}

it('picks meat with the earliest use-by date first, across lots', function () {
    [$a, $b] = twoLotsOfLeg();

    $order = legOrder(creditCustomer(), '30');

    expect($order->lines)->toHaveCount(2)
        ->and($order->lines->pluck('quantity')->map(fn ($q) => (string) $q)->all())->toBe(['20.000', '10.000'])
        ->and($order->lines[0]->meatLine->meat_production_batch_id)->toBe($a->id)->and($order->lines[1]->meatLine->meat_production_batch_id)->toBe($b->id)
        ->and($order->lines[0]->description)->toContain($a->number)->toContain('use by')
        ->and($order->total_minor)->toBe(7500000);   // 30 kg at 2,500.00
});

it('sells the lot that was named', function () {
    [$a, $b] = twoLotsOfLeg();
    $lotB = $b->lines->first(fn ($l) => $l->product->code === 'LEG');

    $order = legOrder(creditCustomer(), '8', ['meat_production_line_id' => $lotB->id]);

    expect($order->lines)->toHaveCount(1)->and($order->lines->sole()->meat_production_line_id)->toBe($lotB->id);
});

it('refuses more meat than is free, counting what other orders hold', function () {
    twoLotsOfLeg();   // 40 kg in all
    $customer = creditCustomer();

    expect(fn () => legOrder($customer, '41'))->toThrow(DomainException::class, 'Only 40.000 kg of Leg is free');

    confirmed(legOrder($customer, '35'));
    expect(fn () => legOrder(creditCustomer(over: ['name' => 'Other']), '6'))->toThrow(DomainException::class, 'Only 5.000 kg of Leg is free');
    expect(legOrder(creditCustomer(over: ['name' => 'Third']), '5')->lines)->not->toBeEmpty();
});

it('checks the weight, the cold room and the product', function () {
    makeMeat();
    $create = fn (array $line) => app(CreateSalesOrder::class)(creditCustomer(), [['kind' => 'meat'] + $line], now());
    $ok = ['meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => '5', 'unit_price_minor' => 100];

    expect(fn () => $create(['quantity' => '0'] + $ok))->toThrow(DomainException::class, 'weight of meat');
    expect(fn () => $create(['quantity' => '1.2345'] + $ok))->toThrow(DomainException::class, 'weight of meat');
    expect(fn () => $create(['inventory_location_id' => null] + $ok))->toThrow(DomainException::class, 'cold room');
    expect(fn () => $create(['meat_product_id' => 99999] + $ok))->toThrow(DomainException::class, 'meat product');
    expect(fn () => $create(['unit_price_minor' => null] + $ok))->toThrow(DomainException::class, 'price');

    MeatProduct::firstWhere('code', 'LEG')->update(['is_active' => false]);
    expect(fn () => $create($ok))->toThrow(DomainException::class, 'meat product');
});

it('takes the price from the price list when none is given', function () {
    makeMeat();
    $list = PriceList::create(['farm_id' => Farm::first()->id, 'category_id' => lookup(LookupCategory::PriceCategory, 'meat'), 'code' => 'MEAT', 'name' => 'Meat', 'currency_code' => 'NGN', 'is_active' => true]);
    PriceListItem::create(['price_list_id' => $list->id, 'code' => 'LEG', 'description' => 'Leg per kg', 'unit_id' => UnitOfMeasure::firstWhere('code', 'KG')->id, 'unit_price_minor' => 320000, 'inventory_item_id' => InventoryItem::firstWhere('code', 'MEAT-LEG')->id]);

    $order = app(CreateSalesOrder::class)(creditCustomer(), [['kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => '10']], now());

    expect($order->total_minor)->toBe(3200000);
});

it('reserves the meat on confirmation and gives it back on cancellation', function () {
    makeMeat();
    $order = confirmed(legOrder(creditCustomer(), '15'));

    expect($order->status)->toBe(S::Confirmed)->and($order->lines->sole()->reservations()->sole()->quantity)->toBe('15.000')
        ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'MEAT-LEG')->id))->toBe('20.000');

    app(CancelSalesOrder::class)($order, 'Customer withdrew');
    expect(fn () => app(ConfirmSalesOrder::class)(legOrder(creditCustomer(over: ['name' => 'B']), '20'), $this->manager))->not->toThrow(DomainException::class);
});

it('refuses to confirm meat that passed its use-by date or whose batch was reversed', function () {
    $batch = makeMeat();
    $order = legOrder(creditCustomer(), '5');

    $this->travel(6)->days();   // leg is good for 5 days
    expect(fn () => app(ConfirmSalesOrder::class)($order, $this->manager))->toThrow(DomainException::class, 'passed its use-by date');
    $this->travelBack();

    app(ReverseMeatProduction::class)($batch, 'Entered in error');
    expect(fn () => app(ConfirmSalesOrder::class)(legOrder(creditCustomer(over: ['name' => 'C']), '5'), $this->manager))->toThrow(DomainException::class);
});

it('dispatches meat out of the cold room and invoices it, keeping the lot on the invoice', function () {
    [$a, $b] = twoLotsOfLeg();
    $customer = creditCustomer();

    $invoice = dispatched(legOrder($customer, '30'));

    $sales = InventoryTransaction::where('type', T::Sale)->orderBy('id')->get();
    expect($invoice->total_minor)->toBe(7500000)->and($invoice->lines)->toHaveCount(2)
        ->and($invoice->lines->pluck('meat_production_line_id')->all())->toBe([$a->lines->first(fn ($l) => $l->product->code === 'LEG')->id, $b->lines->first(fn ($l) => $l->product->code === 'LEG')->id])
        ->and($sales->pluck('quantity')->map(fn ($q) => (string) $q)->all())->toBe(['-20.000', '-10.000'])
        ->and($sales->pluck('source_type')->unique()->all())->toBe(['sales_order'])->and($sales->pluck('source_id')->unique()->all())->toBe([$invoice->sales_order_id])
        ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'MEAT-LEG')->id))->toBe('10.000');
});

it('will not dispatch meat past its use-by date', function () {
    makeMeat();
    $order = confirmed(legOrder(creditCustomer(), '5'));

    $this->travel(6)->days();
    expect(fn () => app(DispatchSalesOrder::class)($order, now()->startOfDay()))->toThrow(DomainException::class, 'expired');
    expect(Invoice::count())->toBe(0);
});

it('lists what to pick, and from where, for every kind of line', function () {
    $batch = makeMeat();
    $released = releasedSemen();
    $pig = register(['category_id' => categoryId('grower')]);
    $pig->update(['current_pen_id' => newPen('P9')->id]);
    $order = app(CreateSalesOrder::class)(creditCustomer(), [
        ['kind' => 'meat', 'meat_product_id' => MeatProduct::firstWhere('code', 'LOIN')->id, 'inventory_location_id' => store('COLD1')->id, 'quantity' => '4', 'unit_price_minor' => 1],
        ['kind' => 'semen', 'semen_batch_id' => $released->id, 'inventory_location_id' => store('SEMEN')->id, 'quantity' => 3, 'unit_price_minor' => 1],
        ['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'head', 'unit_price_minor' => 1],
    ], now());

    $list = app(GetPickingList::class)($order);

    expect($list)->toHaveCount(3)
        ->and($list[0])->toMatchArray(['what' => 'Loin', 'from' => 'Cold room 1', 'batch' => $batch->number, 'quantity' => '4.000 kg'])
        ->and($list[0]['use_by'])->toBe(now()->addDays(5)->format('d M Y'))
        ->and($list[1])->toMatchArray(['what' => 'Semen doses', 'from' => 'Semen laboratory store', 'batch' => $released->number, 'quantity' => '3 doses'])
        ->and($list[2])->toMatchArray(['what' => "Pig {$pig->animal_number}", 'from' => 'P9', 'quantity' => '1 head']);
});
