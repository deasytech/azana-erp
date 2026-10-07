<?php

use App\Domain\Meat\Actions\ProduceMeat;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Slaughter\Actions\RecordSlaughter;
use App\Domain\Slaughter\Actions\RecordSlaughterIntake;
use App\Domain\Traceability\Actions\TraceProduct;
use App\Enums\AnteMortemResult;
use App\Enums\PostMortemResult;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

function labels(array $trace, string $type): array
{
    return collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->where('type', $type)->pluck('label')->all();
}

it('traces a meat batch back to the supplier and the semen batch, and on to the customer', function () {
    $c = traceChain();
    $trace = app(TraceProduct::class)->forMeatBatch($c['meat']);

    expect($trace['subject'])->toMatchArray(['type' => 'meat_production_batch', 'label' => $c['meat']->number]);

    // Slaughter -> the pig
    expect(labels($trace, 'slaughter_batch'))->toBe([$c['day']->number])->and(labels($trace, 'carcass'))->toBe([$c['carcass']->number])
        ->and(labels($trace, 'pen'))->toBe(['Pen TRC1'])->and(labels($trace, 'production_batch'))->toBe([$c['batch']->code]);

    // The pig -> its litter, sow, boar and semen batch
    expect(labels($trace, 'litter'))->toBe([$c['litter']->litter_number])->and(labels($trace, 'semen_batch'))->toBe([$c['semen']->number])
        ->and(labels($trace, 'animal'))->toContain($c['pig']->animal_number, $c['sow']->animal_number, $c['semen']->boar->animal_number);

    // Feed -> the finished feed batch -> raw materials -> supplier
    expect(labels($trace, 'feed_type'))->toBe(['Grower'])->and(labels($trace, 'feed_production_batch'))->toHaveCount(1)
        ->and(labels($trace, 'inventory_batch'))->toContain('Item PREMIX, batch PX-1')->and(labels($trace, 'supplier'))->toBe(['Supplier SUP1']);

    // Downstream: the invoice and the customer
    expect(labels($trace, 'invoice'))->toBe([$c['meatInvoice']->number])->and(labels($trace, 'customer'))->toBe(['City Meats']);
});

it('links the stages so that supplier and customer are reachable from the meat', function () {
    $c = traceChain();
    $trace = app(TraceProduct::class)->forMeatBatch($c['meat']);
    $supplier = collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->firstWhere('type', 'supplier');
    $customer = collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->firstWhere('type', 'customer');
    $semen = collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->firstWhere('type', 'semen_batch');

    expect($trace['upstream'])->toContain($supplier['key'])->toContain($semen['key'])->not->toContain($customer['key'])
        ->and($trace['downstream'])->toContain($customer['key'])->not->toContain($supplier['key']);

    // The customer of the semen is not in the meat's story, nor the semen batch's customer in the meat's downstream.
    expect(collect($trace['stages'])->flatMap(fn ($s) => $s['nodes'])->where('type', 'customer')->pluck('label')->all())->toBe(['City Meats']);
    expect(collect($trace['edges'])->contains(fn ($e) => str_starts_with($e['from'], 'supplier:') && str_starts_with($e['to'], 'inventory_batch:')))->toBeTrue();
});

it('orders the stages from the farm\'s inputs to the customer', function () {
    $trace = app(TraceProduct::class)->forMeatBatch(traceChain()['meat']);

    $titles = array_column($trace['stages'], 'title');

    expect(array_search('Suppliers', $titles))->toBeLessThan(array_search('Feed', $titles))
        ->and(array_search('Feed', $titles))->toBeLessThan(array_search('Pig', $titles))
        ->and(array_search('Pig', $titles))->toBeLessThan(array_search('Slaughter', $titles))
        ->and(array_search('Slaughter', $titles))->toBeLessThan(array_search('Meat', $titles))
        ->and(array_search('Meat', $titles))->toBeLessThan(array_search('Customers', $titles));
});

it('traces a pig up to its parents and down to the customers who bought its meat', function () {
    $c = traceChain();
    $trace = app(TraceProduct::class)->forAnimal($c['pig']);

    expect($trace['subject'])->toMatchArray(['type' => 'animal', 'label' => $c['pig']->animal_number])
        ->and(labels($trace, 'semen_batch'))->toBe([$c['semen']->number])->and(labels($trace, 'supplier'))->toBe(['Supplier SUP1'])
        ->and(labels($trace, 'meat_production_batch'))->toBe([$c['meat']->number])->and(labels($trace, 'customer'))->toBe(['City Meats'])
        ->and(labels($trace, 'carcass'))->toBe([$c['carcass']->number]);
});

it('traces a semen batch to its boar, the sows it served and the customers who bought it', function () {
    $c = traceChain();
    $trace = app(TraceProduct::class)->forSemenBatch($c['semen']);

    expect($trace['subject'])->toMatchArray(['type' => 'semen_batch', 'label' => $c['semen']->number])
        ->and(labels($trace, 'animal'))->toContain($c['semen']->boar->animal_number, $c['sow']->animal_number)
        ->and(labels($trace, 'litter'))->toBe([$c['litter']->litter_number])
        ->and(labels($trace, 'invoice'))->toBe([$c['semenInvoice']->number])->and(labels($trace, 'customer'))->toBe(['Stud Farm']);
});

it('traces a pig sold alive to its customer', function () {
    $pig = register(['category_id' => categoryId('grower')]);
    $invoice = dispatched(app(CreateSalesOrder::class)(creditCustomer(over: ['name' => 'Live Buyer']), [['kind' => 'pig_animal', 'animal_id' => $pig->id, 'unit' => 'head', 'unit_price_minor' => 5000000]], now()));

    $trace = app(TraceProduct::class)->forAnimal($pig);

    expect(labels($trace, 'invoice'))->toBe([$invoice->number])->and(labels($trace, 'customer'))->toBe(['Live Buyer']);
});

it('traces meat from pigs slaughtered as a group through their batch', function () {
    $mill = millFixture();
    completedFeedRun($mill);
    $batch = openBatch(['count' => 10]);
    feed($batch, '30', extra: ['inventory_location_id' => $mill['out']->id]);
    $day = slaughterDay();
    $carcass = app(RecordSlaughter::class)(app(RecordSlaughterIntake::class)($day, $batch, '200.00', AnteMortemResult::Passed, null, 2, 0), '152.00', PostMortemResult::Passed);
    $meat = app(ProduceMeat::class)([$carcass->id], store('COLD1'), now()->startOfDay(), [['meat_product_id' => MeatProduct::firstWhere('code', 'LOIN')->id, 'weight_kg' => '60.00']]);

    $trace = app(TraceProduct::class)->forMeatBatch($meat);

    expect(labels($trace, 'production_batch'))->toBe([$batch->code])->and(labels($trace, 'feed_type'))->toBe(['Grower'])
        ->and(labels($trace, 'supplier'))->toBe(['Supplier SUP1'])->and(labels($trace, 'invoice'))->toBe([])->and($trace['downstream'])->toBe([]);
});

it('says only what it knows when the origin is not recorded', function () {
    $pig = register(['category_id' => categoryId('grower')]);
    $carcass = app(RecordSlaughter::class)(app(RecordSlaughterIntake::class)(slaughterDay(), $pig, '100.00', AnteMortemResult::Passed, null, 1, 0), '76.00', PostMortemResult::Passed);
    $meat = app(ProduceMeat::class)([$carcass->id], store('COLD1'), now()->startOfDay(), [['meat_product_id' => MeatProduct::firstWhere('code', 'LEG')->id, 'weight_kg' => '20.00']]);

    $trace = app(TraceProduct::class)->forMeatBatch($meat);

    expect(labels($trace, 'animal'))->toBe([$pig->animal_number])->and(labels($trace, 'litter'))->toBe([])->and(labels($trace, 'supplier'))->toBe([])->and(labels($trace, 'feed_type'))->toBe([]);
});
