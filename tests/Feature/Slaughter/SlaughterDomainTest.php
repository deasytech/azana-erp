<?php

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Meat\Actions\GetMeatStock;
use App\Domain\Meat\Actions\GetMeatTrace;
use App\Domain\Meat\Actions\ProduceMeat;
use App\Domain\Meat\Actions\ReverseMeatProduction;
use App\Domain\Meat\Models\MeatProduct;
use App\Domain\Meat\Models\MeatProductionBatch;
use App\Domain\Sales\Actions\ConfirmSalesOrder;
use App\Domain\Sales\Actions\CreateSalesOrder;
use App\Domain\Slaughter\Actions\AdjustCarcassWeight;
use App\Domain\Slaughter\Actions\GetSlaughterYield;
use App\Domain\Slaughter\Actions\ManageSlaughterBatch;
use App\Domain\Slaughter\Actions\RecordSlaughter;
use App\Domain\Slaughter\Actions\RecordSlaughterIntake;
use App\Domain\Slaughter\Models\Carcass;
use App\Domain\Slaughter\Models\SlaughterRecord;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\AnteMortemResult as Ante;
use App\Enums\CarcassStatus;
use App\Enums\InventoryTransactionType as T;
use App\Enums\MeatProductionStatus;
use App\Enums\PostMortemResult as Post;
use App\Enums\QuarantineType;
use App\Enums\SlaughterBatchStatus;
use App\Enums\SlaughterRecordStatus as R;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

describe('slaughter days', function () {
    it('are scheduled, worked and closed', function () {
        $manage = app(ManageSlaughterBatch::class);
        $day = $manage->schedule(now()->addDay(), 'Friday kill');

        expect($day->number)->toBe('SB-000001')->and($day->status)->toBe(SlaughterBatchStatus::Scheduled);
        expect(fn () => $manage->schedule(now()->subDays(10)))->toThrow(DomainException::class, 'more than a week');
        expect(fn () => $manage->complete($day))->toThrow(DomainException::class, 'Nothing was received');

        $record = receivePig($day);
        expect($day->fresh()->status)->toBe(SlaughterBatchStatus::InProgress);
        expect(fn () => $manage->complete($day))->toThrow(DomainException::class, 'still waiting');

        app(RecordSlaughter::class)($record, '76.00', Post::Passed);
        expect($manage->complete($day)->status)->toBe(SlaughterBatchStatus::Completed);
        expect(fn () => receivePig($day))->toThrow(DomainException::class, 'takes no more pigs');
    });

    it('can be cancelled before anything is slaughtered, and sends received pigs back', function () {
        $manage = app(ManageSlaughterBatch::class);
        $day = slaughterDay();
        $record = receivePig($day);

        expect(fn () => $manage->cancel($day, ' '))->toThrow(DomainException::class, 'reason');
        $manage->cancel($day, 'Power cut');

        expect($day->fresh()->status)->toBe(SlaughterBatchStatus::Cancelled)->and($record->fresh()->status)->toBe(R::Rejected)->and($record->animal->fresh()->status)->toBe(AnimalStatus::Active);

        $second = slaughterDay();
        app(RecordSlaughter::class)(receivePig($second), '76.00', Post::Passed);
        expect(fn () => $manage->cancel($second, 'Too late'))->toThrow(DomainException::class, 'already been slaughtered');
    });
});

describe('intake and inspection', function () {
    it('receives a tracked pig, records its weight and takes its cost from the farm records', function () {
        $day = slaughterDay();
        $pig = register(['category_id' => categoryId('grower')]);
        app(RecordFeedConsumption::class)($pig, growerFeed(), now()->startOfDay(), '50', ['cost_per_kg_minor' => 25000]);   // 12,500.00 of feed

        $record = app(RecordSlaughterIntake::class)($day, $pig, '102.50', Ante::Passed, 'Alert, clean');

        expect($record->status)->toBe(R::Received)->and((string) $record->live_weight_kg)->toBe('102.50')->and($record->live_cost_minor)->toBe(1250000)
            ->and($record->heads)->toBe(1)->and($pig->latestWeight()->weight_kg)->toBe('102.50');
    });

    it('takes the cost of pigs from a batch from the batch\'s cost per pig', function () {
        $batch = openBatch(['count' => 10, 'unit_cost_minor' => 500000]);   // each pig cost 5,000.00 to buy
        $day = slaughterDay();

        $record = app(RecordSlaughterIntake::class)($day, $batch, '300.00', Ante::Passed, null, 3);

        expect($record->heads)->toBe(3)->and($record->live_cost_minor)->toBe(1500000)->and($record->production_batch_id)->toBe($batch->id);
    });

    it('takes a pig that fails inspection no further', function () {
        $day = slaughterDay();
        $pig = register(['category_id' => categoryId('grower')]);

        expect(fn () => app(RecordSlaughterIntake::class)($day, $pig, '90', Ante::Failed))->toThrow(DomainException::class, 'why the pig failed');

        $record = app(RecordSlaughterIntake::class)($day, $pig, '90', Ante::Failed, 'Lame and feverish');

        expect($record->status)->toBe(R::Rejected)->and($pig->fresh()->status)->toBe(AnimalStatus::Active);
        expect(fn () => app(RecordSlaughter::class)($record, '60', Post::Passed))->toThrow(DomainException::class, 'only a pig that was received');
    });

    it('refuses a pig under a medication withdrawal or in quarantine', function () {
        $day = slaughterDay();
        $treated = register(['category_id' => categoryId('grower')]);
        treat($treated, medicine(30), now()->subDays(2)->toDateString());
        $sick = register(['category_id' => categoryId('grower')]);
        app(StartQuarantine::class)($sick, QuarantineType::Isolation, now()->startOfDay(), 'Coughing');

        expect(fn () => app(RecordSlaughterIntake::class)($day, $treated, '90', Ante::Passed))->toThrow(DomainException::class, 'under withdrawal');
        expect(fn () => app(RecordSlaughterIntake::class)($day, $sick, '90', Ante::Passed))->toThrow(DomainException::class, 'cannot be sold or slaughtered');
        expect(SlaughterRecord::count())->toBe(0);
    });

    it('refuses a pig that is not active, already received or reserved for a customer', function () {
        $day = slaughterDay();
        $gone = register(['category_id' => categoryId('grower')]);
        app(ChangeAnimalStatus::class)($gone, AnimalStatus::Sold, 'Sold', now());
        $pig = register(['category_id' => categoryId('grower')]);
        receivePig($day, $pig);
        $reserved = register(['category_id' => categoryId('grower')]);
        app(ConfirmSalesOrder::class)(app(CreateSalesOrder::class)(creditCustomer(), [['kind' => 'pig_animal', 'animal_id' => $reserved->id, 'unit' => 'head', 'unit_price_minor' => 5000000]], now()), userWithRole('Farm Manager'));

        expect(fn () => app(RecordSlaughterIntake::class)($day, $gone, '90', Ante::Passed))->toThrow(DomainException::class, 'Sold');
        expect(fn () => app(RecordSlaughterIntake::class)($day, $pig, '90', Ante::Passed))->toThrow(DomainException::class, 'already been received');
        expect(fn () => app(RecordSlaughterIntake::class)($day, $reserved, '90', Ante::Passed))->toThrow(DomainException::class, 'reserved for a customer');
    });

    it('checks the weight, the date and how many pigs are free in a batch', function () {
        $day = slaughterDay();
        $pig = register(['category_id' => categoryId('grower')]);
        $intake = app(RecordSlaughterIntake::class);

        expect(fn () => $intake($day, $pig, '0', Ante::Passed))->toThrow(DomainException::class, 'live weight');
        expect(fn () => $intake($day, $pig, '501', Ante::Passed))->toThrow(DomainException::class, 'live weight');
        expect(fn () => $intake($day, $pig, '90', Ante::Passed, null, 1, null, now()->addDay()))->toThrow(DomainException::class, 'future');

        $batch = openBatch(['count' => 5]);
        $intake($day, $batch, '300', Ante::Passed, null, 3);
        expect(fn () => $intake($day, $batch, '300', Ante::Passed, null, 3))->toThrow(DomainException::class, 'only 2 pigs are free');
        expect(fn () => $intake($day, $batch, '1500', Ante::Passed, null, 2))->toThrow(DomainException::class, 'live weight');
    });
});

describe('slaughter and the carcass', function () {
    it('records the carcass with its dressing percentage and marks the pig slaughtered', function () {
        $day = slaughterDay();
        $record = receivePig($day);
        $pig = $record->animal;

        $carcass = app(RecordSlaughter::class)($record, '76.00', Post::Passed, null, 'Clean');

        expect($carcass->number)->toBe('CAR-000001')->and((string) $carcass->dressing_percent)->toBe('76.00')->and($carcass->status)->toBe(CarcassStatus::Hanging)
            ->and($carcass->usableKg())->toBe('76.00')->and($record->fresh()->status)->toBe(R::Slaughtered)
            ->and($pig->fresh()->status)->toBe(AnimalStatus::Slaughtered)->and($carcass->record->animal->id)->toBe($pig->id);
    });

    it('takes slaughtered pigs of a batch off the batch', function () {
        $batch = openBatch(['count' => 10]);
        $record = app(RecordSlaughterIntake::class)(slaughterDay(), $batch, '300.00', Ante::Passed, null, 3);

        $carcass = app(RecordSlaughter::class)($record, '228.00', Post::Passed);

        expect($batch->fresh()->headCount())->toBe(7)->and((string) $carcass->dressing_percent)->toBe('76.00');
    });

    it('subtracts what the inspector condemns, and writes off a fully condemned carcass', function () {
        $partial = app(RecordSlaughter::class)(receivePig(slaughterDay()), '76.00', Post::Partial, '6.50', 'Abscess in the shoulder');
        expect((string) $partial->condemned_kg)->toBe('6.50')->and($partial->usableKg())->toBe('69.50')->and($partial->status)->toBe(CarcassStatus::Hanging);

        $record = receivePig(slaughterDay());
        $condemned = app(RecordSlaughter::class)($record, '75.00', Post::Condemned);
        expect($condemned->status)->toBe(CarcassStatus::Condemned)->and($condemned->usableKg())->toBe('0.00')->and($record->fresh()->status)->toBe(R::Condemned)
            ->and($record->animal->fresh()->status)->toBe(AnimalStatus::Slaughtered);
    });

    it('checks the weights and dates', function () {
        $record = receivePig(slaughterDay());
        $slaughter = app(RecordSlaughter::class);

        expect(fn () => $slaughter($record, '0', Post::Passed))->toThrow(DomainException::class, 'above zero');
        expect(fn () => $slaughter($record, '100.01', Post::Passed))->toThrow(DomainException::class, 'live weight of 100.00');
        expect(fn () => $slaughter($record, '76', Post::Partial))->toThrow(DomainException::class, 'weight condemned');
        expect(fn () => $slaughter($record, '76', Post::Partial, '76'))->toThrow(DomainException::class, 'weight condemned');
        expect(fn () => $slaughter($record, '76', Post::Passed, null, null, now()->addDay()))->toThrow(DomainException::class, 'future');
        expect(fn () => $slaughter($record, '76', Post::Passed, null, null, now()->subDay()))->toThrow(DomainException::class, 'before the pig was received');

        $slaughter($record, '76', Post::Passed);
        expect(fn () => $slaughter($record, '76', Post::Passed))->toThrow(DomainException::class, 'only a pig that was received');
        expect(Carcass::count())->toBe(1);
    });

    it('does nothing if the pig fell under a withdrawal after it was received', function () {
        $record = receivePig(slaughterDay());
        treat($record->animal, medicine(30), now()->subDay()->toDateString());

        expect(fn () => app(RecordSlaughter::class)($record, '76.00', Post::Passed))->toThrow(DomainException::class, 'under withdrawal');

        expect(Carcass::count())->toBe(0)->and($record->fresh()->status)->toBe(R::Received)->and($record->animal->fresh()->status)->toBe(AnimalStatus::Active);
    });

    it('corrects a carcass weight only with approval, and keeps the correction', function () {
        $carcass = slaughterPig();
        $adjust = app(AdjustCarcassWeight::class);
        $manager = userWithRole('Slaughter Manager');

        expect(fn () => $adjust($carcass, '74.00', 'Scale re-read', farmWorker()))->toThrow(DomainException::class, 'not authorised');
        expect(fn () => $adjust($carcass, '74.00', ' ', $manager))->toThrow(DomainException::class, 'reason');
        expect(fn () => $adjust($carcass, '120.00', 'Typo', $manager))->toThrow(DomainException::class, 'no more than the live weight');

        $corrected = $adjust($carcass, '74.00', 'Scale re-read', $manager);

        expect((string) $corrected->hot_weight_kg)->toBe('74.00')->and((string) $corrected->dressing_percent)->toBe('74.00');
        $row = $carcass->adjustments()->sole();
        expect((string) $row->old_hot_weight_kg)->toBe('76.00')->and($row->approved_by)->toBe($manager->id)->and($row->reason)->toBe('Scale re-read');
        expect(fn () => $row->update(['reason' => 'x']))->toThrow(LogicException::class);
    });

    it('cannot be corrected by whoever recorded it, nor after it has been processed', function () {
        $manager = userWithRole('Slaughter Manager');
        $carcass = app(RecordSlaughter::class)(receivePig(slaughterDay()), '76.00', Post::Passed, null, null, null, $manager);

        expect(fn () => app(AdjustCarcassWeight::class)($carcass, '74.00', 'Re-read', $manager))->toThrow(DomainException::class, 'someone other than');

        makeMeat($carcass);
        expect(fn () => app(AdjustCarcassWeight::class)($carcass->fresh(), '74.00', 'Re-read', userWithRole('Farm Manager')))->toThrow(DomainException::class, 'has not been processed');
    });
});

describe('meat production', function () {
    it('puts the products into the cold room and shares the cost by weight', function () {
        $carcass = slaughterPig();    // 76.00 kg hot carcass, 20,000.00 to raise
        $batch = makeMeat($carcass);  // 67.00 kg of products, 6.00 kg waste, 1,000.00 other costs

        expect($batch->number)->toBe('IPA-MT-'.now()->format('Ymd').'-001')->and((string) $batch->input_kg)->toBe('76.00')->and((string) $batch->output_kg)->toBe('67.00')->and((string) $batch->waste_kg)->toBe('6.00')
            ->and($batch->live_cost_minor)->toBe(2000000)->and($batch->total_cost_minor)->toBe(2100000);

        expect($batch->lines->mapWithKeys(fn ($l) => [$l->product->code => $l->cost_minor])->all())
            ->toBe(['LEG' => 626866, 'LOIN' => 470149, 'SHOULDER' => 564179, 'BELLY' => 376119, 'LIVER' => 62687]);
        expect($batch->lines->sum('cost_minor'))->toBe(2100000);

        $carcass->refresh();
        expect($carcass->status)->toBe(CarcassStatus::Processed)->and($carcass->meat_production_batch_id)->toBe($batch->id);
    });

    it('enters every product into inventory through a ledger transaction with its use-by date', function () {
        $batch = makeMeat();
        $leg = $batch->lines->first(fn ($l) => $l->product->code === 'LEG');
        $liver = $batch->lines->first(fn ($l) => $l->product->code === 'LIVER');

        $stock = app(GetStockLevels::class)(itemId: InventoryItem::firstWhere('code', 'MEAT-LEG')->id)->sole();
        $ledger = InventoryTransaction::where('group_uuid', $batch->group_uuid)->get();

        expect($stock->on_hand)->toBe('20.000')->and($stock->value_minor)->toBe(626866)->and($stock->location->code)->toBe('COLD1')->and($stock->batch->batch_number)->toBe($batch->number)
            ->and($leg->use_by->toDateString())->toBe(now()->addDays(5)->toDateString())->and($liver->use_by->toDateString())->toBe(now()->addDays(3)->toDateString())
            ->and($ledger)->toHaveCount(5)->and($ledger->pluck('type')->unique()->all())->toBe([T::Production])->and($ledger->pluck('source_type')->unique()->all())->toBe(['meat_production'])
            ->and($leg->inventoryBatch->expiry_date->toDateString())->toBe($leg->use_by->toDateString());
    });

    it('cannot make more meat than the carcasses weigh', function () {
        $carcass = slaughterPig();   // 76.00 kg

        expect(fn () => app(ProduceMeat::class)([$carcass->id], store('COLD1'), now(), meatLines(['LEG' => '70.00']), '6.01'))->toThrow(DomainException::class, 'the carcasses only provide 76.00 kg');
        expect(app(ProduceMeat::class)([$carcass->id], store('COLD1'), now(), meatLines(['LEG' => '70.00']), '6.00')->status)->toBe(MeatProductionStatus::Produced);
    });

    it('uses only what the inspector left, from one or several carcasses', function () {
        $day = slaughterDay();
        $a = app(RecordSlaughter::class)(receivePig($day, cost: 1000000), '76.00', Post::Partial, '6.00');   // 70.00 usable
        $b = app(RecordSlaughter::class)(receivePig($day, cost: 3000000), '80.00', Post::Passed);           // 80.00 usable

        expect(fn () => app(ProduceMeat::class)([$a->id], store('COLD1'), now(), meatLines(['LEG' => '71.00'])))->toThrow(DomainException::class, 'only provide 70.00 kg');

        $batch = app(ProduceMeat::class)([$a->id, $b->id], store('COLD1'), now(), meatLines(['LEG' => '100.00', 'LOIN' => '50.00']), '0', 500000);

        expect((string) $batch->input_kg)->toBe('150.00')->and($batch->live_cost_minor)->toBe(4000000)->and($batch->total_cost_minor)->toBe(4500000)
            ->and($batch->lines->pluck('cost_minor')->all())->toBe([3000000, 1500000]);
    });

    it('refuses carcasses that cannot be made into meat', function () {
        $produce = fn ($carcass, $on = null) => app(ProduceMeat::class)([$carcass->id], store('COLD1'), $on ?? now(), meatLines(['LEG' => '10.00']));
        $condemned = app(RecordSlaughter::class)(receivePig(slaughterDay()), '75.00', Post::Condemned);
        $done = slaughterPig();
        makeMeat($done);

        expect(fn () => $produce($condemned))->toThrow(DomainException::class, 'only a carcass hanging');
        expect(fn () => $produce($done->fresh()))->toThrow(DomainException::class, 'only a carcass hanging');
        expect(fn () => app(ProduceMeat::class)([], store('COLD1'), now(), meatLines(['LEG' => '1'])))->toThrow(DomainException::class, 'at least one carcass');
        expect(fn () => $produce(slaughterPig(), now()->subDay()))->toThrow(DomainException::class, 'cannot be made before then');
    });

    it('checks the products and leaves nothing behind if a line fails', function () {
        $carcass = slaughterPig();
        $stock = InventoryTransaction::count();
        $produce = fn (array $lines, $location = null) => app(ProduceMeat::class)([$carcass->id], $location ?? store('COLD1'), now(), $lines);

        expect(fn () => $produce([]))->toThrow(DomainException::class, 'what the carcasses were made into');
        expect(fn () => $produce([['meat_product_id' => 99999, 'weight_kg' => '1']]))->toThrow(DomainException::class, 'catalogue');
        expect(fn () => $produce(meatLines(['LEG' => '0'])))->toThrow(DomainException::class, 'positive');
        expect(fn () => $produce(array_merge(meatLines(['LEG' => '5.00']), meatLines(['LEG' => '5.00']))))->toThrow(DomainException::class, 'appears twice');

        MeatProduct::firstWhere('code', 'LOIN')->update(['is_active' => false]);
        expect(fn () => $produce(meatLines(['LEG' => '5.00', 'LOIN' => '5.00'])))->toThrow(DomainException::class, 'not an active product');

        MeatProduct::firstWhere('code', 'RIBS')->item->update(['unit_id' => UnitOfMeasure::firstWhere('code', 'BAG')->id]);
        expect(fn () => $produce(meatLines(['LEG' => '5.00', 'RIBS' => '5.00'])))->toThrow(DomainException::class, 'stocked in kg');

        $closed = store('CLOSED');
        $closed->update(['is_active' => false]);
        expect(fn () => $produce(meatLines(['LEG' => '5.00']), $closed))->toThrow(DomainException::class, 'active cold room');

        expect(InventoryTransaction::count())->toBe($stock)->and($carcass->fresh()->status)->toBe(CarcassStatus::Hanging)->and(MeatProductionBatch::count())->toBe(0);
    });

    it('numbers the day\'s batches in turn', function () {
        $first = makeMeat();
        $second = makeMeat();

        expect($first->number)->toEndWith('-001')->and($second->number)->toEndWith('-002');
    });

    it('can be reversed, putting the carcasses back, unless any meat has been used', function () {
        $carcass = slaughterPig();
        $batch = makeMeat($carcass);

        expect(fn () => app(ReverseMeatProduction::class)($batch, ' '))->toThrow(DomainException::class, 'reason');

        app(ReverseMeatProduction::class)($batch, 'Entered against the wrong carcass');

        expect($batch->fresh()->status)->toBe(MeatProductionStatus::Reversed)->and($carcass->fresh()->status)->toBe(CarcassStatus::Hanging)->and($carcass->fresh()->meat_production_batch_id)->toBeNull()
            ->and(app(GetStockLevels::class)())->toBeEmpty();
        expect(fn () => app(ReverseMeatProduction::class)($batch, 'Again'))->toThrow(DomainException::class, 'Reversed');

        $again = makeMeat($carcass->fresh());
        issueStock(InventoryItem::firstWhere('code', 'MEAT-LEG'), '5', store('COLD1'), T::Sale);
        expect(fn () => app(ReverseMeatProduction::class)($again, 'Too late'))->toThrow(DomainException::class, 'already been used');
        expect($again->fresh()->status)->toBe(MeatProductionStatus::Produced);
    });
});

describe('traceability and reports', function () {
    it('traces meat back to the animal and the production batch', function () {
        $batch = openBatch(['count' => 10]);
        $pig = register(['category_id' => categoryId('grower')]);
        $day = slaughterDay();
        $tracked = app(RecordSlaughter::class)(receivePig($day, $pig), '76.00', Post::Passed);
        $group = app(RecordSlaughter::class)(app(RecordSlaughterIntake::class)($day, $batch, '200.00', Ante::Passed, null, 2, 0), '152.00', Post::Passed);

        $meat = app(ProduceMeat::class)([$tracked->id, $group->id], store('COLD1'), now(), meatLines(['LEG' => '100.00']));
        $trace = app(GetMeatTrace::class)($meat);

        expect($trace)->toHaveCount(2)
            ->and($trace[0])->toMatchArray(['carcass' => $tracked->number, 'animal' => $pig->animal_number, 'production_batch' => null, 'slaughter_day' => $day->number, 'heads' => 1, 'dressing_percent' => '76.00'])
            ->and($trace[1])->toMatchArray(['carcass' => $group->number, 'animal' => null, 'production_batch' => $batch->code, 'heads' => 2]);
    });

    it('lists meat in the cold rooms by product, batch and use-by date', function () {
        $batch = makeMeat();
        issueStock(InventoryItem::firstWhere('code', 'MEAT-LEG'), '5', store('COLD1'), T::Sale);

        $rows = app(GetMeatStock::class)();

        expect($rows)->toHaveCount(5)
            ->and($rows->first()['product'])->toBe('Liver')   // soonest use-by first
            ->and($rows->firstWhere('product', 'Leg'))->toMatchArray(['kg' => '15.000', 'batch' => $batch->number, 'store' => 'Cold room 1', 'expired' => false])
            ->and($rows->firstWhere('product', 'Leg')['value_minor'])->toBe(470149);   // 626,866 less the 5 kg sold (156,717 rounded up)
    });

    it('reports dressing percentage against the target and flags low carcasses', function () {
        $day = slaughterDay();
        app(RecordSlaughter::class)(receivePig($day), '76.00', Post::Passed);                      // 76%
        app(RecordSlaughter::class)(receivePig($day), '60.00', Post::Partial, '5.00');             // 60%: below the 65% alert
        app(RecordSlaughter::class)(receivePig($day, liveKg: '120.00'), '90.00', Post::Passed);   // 75%

        $yield = app(GetSlaughterYield::class)(now()->subDay(), now());

        expect($yield['totals'])->toMatchArray(['carcasses' => 3, 'heads' => 3, 'live_kg' => '320.00', 'hot_kg' => '226.00', 'condemned_kg' => '5.00', 'dressing_percent' => '70.63', 'target_percent' => '75'])
            ->and($yield['by_day'])->toHaveCount(1)
            ->and($yield['low'])->toHaveCount(1)->and((string) $yield['low']->first()->dressing_percent)->toBe('60.00');

        app(ResolveSettings::class)->set('slaughter.min_dressing_percent_alert', 80);
        expect(app(GetSlaughterYield::class)(now()->subDay(), now())['low'])->toHaveCount(3);
        expect(app(GetSlaughterYield::class)(now()->subDays(10), now()->subDays(5))['totals']['dressing_percent'])->toBeNull();
    });
});

it('lets a sold-to-the-abattoir pig not be sold again, and a slaughtered pig not be reserved', function () {
    $carcass = slaughterPig();

    expect(fn () => app(ConfirmSalesOrder::class)(app(CreateSalesOrder::class)(creditCustomer(), [['kind' => 'pig_animal', 'animal_id' => $carcass->record->animal_id, 'unit' => 'head', 'unit_price_minor' => 1]], now()), userWithRole('Farm Manager')))
        ->toThrow(DomainException::class, 'cannot be sold');
});

it('grants slaughter rights by role', function () {
    $manager = userWithRole('Slaughter Manager');

    expect($manager->can('slaughter.approve'))->toBeTrue()->and(userWithRole('Veterinarian')->can('slaughter.create'))->toBeTrue()->and(userWithRole('Veterinarian')->can('slaughter.approve'))->toBeFalse()
        ->and(userWithRole('Sales Officer')->can('slaughter.view'))->toBeTrue()->and(userWithRole('Sales Officer')->can('slaughter.create'))->toBeFalse()
        ->and(farmWorker()->can('slaughter.view'))->toBeFalse()
        ->and(owner()->can('delete', slaughterPig()))->toBeFalse()
        ->and(owner()->can('delete', MeatProduct::firstWhere('code', 'FAT')))->toBeTrue();
});
