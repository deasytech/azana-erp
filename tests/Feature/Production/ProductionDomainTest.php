<?php

use App\Domain\Animal\Actions\ChangeAnimalStatus;
use App\Domain\Animal\Actions\RecordWeight;
use App\Domain\Animal\Actions\VoidWeight;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Breed;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Actions\VoidFeedConsumption;
use App\Domain\Feed\Models\FeedConsumptionRecord;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Health\Actions\GetMortalityAnalysis;
use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Production\Actions\AddAnimalToBatch;
use App\Domain\Production\Actions\AddPigsToBatch;
use App\Domain\Production\Actions\AdjustBatchCount;
use App\Domain\Production\Actions\GetAnimalGrowth;
use App\Domain\Production\Actions\GetBatchPerformance;
use App\Domain\Production\Actions\GetProductionSummary;
use App\Domain\Production\Actions\PostBatchEvent;
use App\Domain\Production\Actions\RecordBatchMortality;
use App\Domain\Production\Actions\RecordBatchWeighIn;
use App\Domain\Production\Actions\RecordProductionCost;
use App\Domain\Production\Actions\RemovePigsFromBatch;
use App\Domain\Production\Actions\VoidBatchWeighIn;
use App\Domain\Production\Actions\VoidProductionCost;
use App\Domain\Production\Models\BatchWeighIn;
use App\Domain\Production\Models\ProductionBatch;
use App\Domain\Production\Models\ProductionBatchEvent;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\BatchEventType;
use App\Enums\BatchStatus;
use App\Enums\LookupCategory;
use App\Enums\ProductionCostCategory;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

it('opens a batch with a numbered code, its first placement and starting weight', function () {
    $batch = openBatch(['average_weight_kg' => '20.00', 'unit_cost_minor' => 500000, 'placed_age_days' => 70, 'pen_id' => newPen('GRW1')->id]);

    expect($batch->code)->toBe('BATCH-'.now()->year.'-001')
        ->and($batch->status)->toBe(BatchStatus::Active)
        ->and($batch->headCount())->toBe(100)
        ->and($batch->events)->toHaveCount(1)
        ->and($batch->events->first()->type)->toBe(BatchEventType::Placement)
        ->and($batch->events->first()->unit_cost_minor)->toBe(500000)
        ->and($batch->weighIns->first()->average_weight_kg)->toBe('20.00')
        ->and($batch->weighIns->first()->sample_size)->toBe(100)
        ->and($batch->pen->code)->toBe('GRW1')
        ->and(openBatch(['name' => 'Second'])->code)->toBe('BATCH-'.now()->year.'-002');
});

it('validates new batches', function (array $bad, string $message) {
    expect(fn () => openBatch($bad))->toThrow(DomainException::class, $message);
})->with([
    'no name' => [['name' => ' '], 'name'],
    'bad stage' => [['stage_id' => 999999], 'valid production stage'],
    'no pigs' => [['count' => 0], 'at least one pig'],
    'future start' => [['started_on' => now()->addDays(2)], 'future'],
    'bad breed' => [['breed_id' => 999999], 'valid breed'],
    'bad target' => [['target_weight_kg' => '0'], 'positive'],
    'heavy start weight' => [['average_weight_kg' => '9999'], 'plausible'],
]);

it('rolls a failed opening back completely', function () {
    expect(fn () => openBatch(['average_weight_kg' => '-5']))->toThrow(DomainException::class);

    expect(ProductionBatch::count())->toBe(0)->and(ProductionBatchEvent::count())->toBe(0);
});

it('keeps head counts in an append-only ledger that cannot go negative', function () {
    $batch = openBatch(['started_on' => now()->subDays(10)->startOfDay(), 'count' => 10]);

    app(AddPigsToBatch::class)($batch, 5, now()->subDays(5)->startOfDay(), BatchEventType::TransferIn, 400000, 'From batch 0');
    app(RemovePigsFromBatch::class)($batch, BatchEventType::Sale, 3, now()->subDay()->startOfDay());
    app(RecordBatchMortality::class)($batch, 2, now()->startOfDay(), lookup(LookupCategory::MortalityCause, 'scours'));
    app(AdjustBatchCount::class)($batch, -1, now()->startOfDay(), 'Physical count');

    expect($batch->headCount())->toBe(9)
        ->and($batch->events()->count())->toBe(5)
        ->and(fn () => app(RemovePigsFromBatch::class)($batch, BatchEventType::Sale, 10, now()->startOfDay()))->toThrow(DomainException::class, 'does not have 10 pigs')
        ->and(fn () => $batch->events->first()->update(['delta' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $batch->events->first()->delete())->toThrow(LogicException::class)
        ->and(owner()->can('delete', $batch))->toBeFalse();
});

it('checks the head count on the event\'s own date for back-dated removals', function () {
    $batch = openBatch(['started_on' => now()->subDays(10)->startOfDay(), 'count' => 10]);
    app(AddPigsToBatch::class)($batch, 20, now()->subDays(2)->startOfDay());

    // 30 pigs today, but only 10 existed 5 days ago.
    expect(fn () => app(RemovePigsFromBatch::class)($batch, BatchEventType::Sale, 15, now()->subDays(5)->startOfDay()))->toThrow(DomainException::class, 'does not have 15')
        ->and(app(RemovePigsFromBatch::class)($batch, BatchEventType::Sale, 10, now()->subDays(5)->startOfDay())->delta)->toBe(-10);
});

it('validates batch events', function () {
    $batch = openBatch(['started_on' => now()->subDays(3)->startOfDay()]);
    $post = app(PostBatchEvent::class);

    expect(fn () => $post($batch, BatchEventType::Sale, 5, now()))->toThrow(DomainException::class, 'must remove')
        ->and(fn () => $post($batch, BatchEventType::Placement, -5, now()))->toThrow(DomainException::class, 'must add')
        ->and(fn () => $post($batch, BatchEventType::Adjustment, 0, now()))->toThrow(DomainException::class)
        ->and(fn () => app(AddPigsToBatch::class)($batch, 1, now()->subDays(9)->startOfDay()))->toThrow(DomainException::class, 'between the batch start and today')
        ->and(fn () => app(AddPigsToBatch::class)($batch, 1, now()->addDay()))->toThrow(DomainException::class, 'between the batch start and today')
        ->and(fn () => app(AddPigsToBatch::class)($batch, 1, now(), BatchEventType::Sale))->toThrow(DomainException::class, 'placement or a transfer in')
        ->and(fn () => app(RemovePigsFromBatch::class)($batch, BatchEventType::Mortality, 1, now()))->toThrow(DomainException::class, 'record deaths as mortality')
        ->and(fn () => app(RecordBatchMortality::class)($batch, 1, now(), 999999))->toThrow(DomainException::class, 'valid cause')
        ->and(fn () => app(AdjustBatchCount::class)($batch, 1, now(), ' '))->toThrow(DomainException::class, 'reason');
});

it('closes a batch when its last pig leaves and then refuses new events', function () {
    $batch = openBatch(['count' => 3]);

    app(RemovePigsFromBatch::class)($batch, BatchEventType::Slaughter, 3, now()->startOfDay());
    $batch->refresh();

    expect($batch->status)->toBe(BatchStatus::Closed)
        ->and($batch->closed_on->isToday())->toBeTrue()
        ->and(fn () => app(AddPigsToBatch::class)($batch, 1, now()))->toThrow(DomainException::class, 'closed')
        ->and(fn () => feed($batch, '10'))->toThrow(DomainException::class, 'closed');
});

it('replays retried batch events and rejects a key used on another batch', function () {
    $batch = openBatch();
    $other = openBatch(['name' => 'Other']);
    $add = fn (ProductionBatch $b) => app(AddPigsToBatch::class)($b, 5, now(), idempotencyKey: 'dev5-0001');

    $first = $add($batch);

    expect($add($batch)->is($first))->toBeTrue()
        ->and($batch->headCount())->toBe(105)
        ->and(fn () => $add($other))->toThrow(DomainException::class, 'different batch');
});

it('counts untracked deaths in the batch and in mortality', function () {
    $batch = openBatch(['count' => 50]);
    app(RecordBatchMortality::class)($batch, 3, now()->startOfDay(), lookup(LookupCategory::MortalityCause, 'respiratory'), 'Pneumonia');

    $perf = app(GetBatchPerformance::class)($batch);

    expect($perf['heads'])->toBe(47)->and($perf['mortality'])->toBe(3)->and($perf['mortality_percent'])->toBe('6.00');
});

it('takes tracked animals in and out of a batch as they join, die, are sold or are culled', function () {
    $batch = openBatch(['count' => 10]);
    [$a, $b, $c, $d] = collect(range(1, 4))->map(fn () => register(['category_id' => categoryId('grower')]))->all();
    $join = app(AddAnimalToBatch::class);
    collect([$a, $b, $c, $d])->each(fn ($animal) => $join($batch, $animal, now()->startOfDay()));

    expect($batch->headCount())->toBe(14)
        ->and(fn () => $join(openBatch(['name' => 'Other']), $a, now()))->toThrow(DomainException::class, 'already belongs');

    app(RecordMortality::class)($a, now(), lookup(LookupCategory::MortalityCause, 'injury'));
    app(ChangeAnimalStatus::class)($b, AnimalStatus::Sold, 'Sold');
    app(ChangeAnimalStatus::class)($c, AnimalStatus::Culled, 'Culled');
    app(ChangeAnimalStatus::class)($d, AnimalStatus::TransferredOut, 'Sent on');

    $types = $batch->events()->whereNotNull('animal_id')->where('delta', '<', 0)->pluck('type')->map->value->sort()->values()->all();

    expect($batch->headCount())->toBe(10)   // 10 + 4 joined - 4 gone
        ->and($types)->toBe(['cull', 'mortality', 'sale', 'transfer_out'])
        ->and($batch->members()->whereNull('left_on')->count())->toBe(0)
        ->and($batch->members()->where('animal_id', $a->id)->first()->left_reason)->toBe('dead')
        ->and(app(ChangeAnimalStatus::class)(register(), AnimalStatus::Dead, 'Not in a batch')->to_status)->toBe(AnimalStatus::Dead);
});

it('refuses inactive animals and does not double count tracked deaths in mortality analysis', function () {
    $batch = openBatch(['count' => 5]);
    $animal = register(['category_id' => categoryId('grower')]);
    app(AddAnimalToBatch::class)($batch, $animal, now()->startOfDay());
    app(RecordMortality::class)($animal, now(), lookup(LookupCategory::MortalityCause, 'injury'));
    app(RecordBatchMortality::class)($batch, 2, now()->startOfDay(), lookup(LookupCategory::MortalityCause, 'scours'));

    expect(fn () => app(AddAnimalToBatch::class)($batch, $animal, now()))->toThrow(DomainException::class, 'cannot join')
        ->and(app(GetMortalityAnalysis::class)(now()->subDay(), now(), 'cause')['total'])->toBe(3);
});

it('includes untracked batch deaths in mortality analysis by stage, pen, cause and age', function () {
    $pen = newPen('FIN1');
    $batch = openBatch(['count' => 40, 'pen_id' => $pen->id, 'placed_age_days' => 60, 'started_on' => now()->subDays(20)->startOfDay(), 'breed_id' => Breed::firstWhere('code', 'LW')->id]);
    app(RecordBatchMortality::class)($batch, 4, now()->startOfDay(), lookup(LookupCategory::MortalityCause, 'respiratory'));
    $by = fn (string $dim) => app(GetMortalityAnalysis::class)(now()->subDay(), now(), $dim)['rows']->mapWithKeys(fn ($r) => [$r['label'] => $r['count']])->all();

    expect($by('stage'))->toBe(['Grower' => 4])
        ->and($by('pen'))->toBe(['FIN1' => 4])
        ->and($by('cause'))->toBe(['Respiratory disease' => 4])
        ->and($by('breed'))->toBe(['Large White' => 4])
        ->and($by('age_band'))->toBe(['71-150 days' => 4]) // 60 days at placement + 20 days
        ->and($by('litter'))->toBe(['Unknown' => 4]);
});

it('records batch weigh-ins and voids wrong ones', function () {
    $batch = openBatch(['started_on' => now()->subDays(20)->startOfDay()]);
    $record = app(RecordBatchWeighIn::class);

    $ok = weighIn($batch, '31.50', 10);
    expect($ok->average_weight_kg)->toBe('31.50')
        ->and(fn () => weighIn($batch, '33', 10))->toThrow(DomainException::class, 'already has a weigh-in')
        ->and(fn () => weighIn($batch, '0', 9))->toThrow(DomainException::class, 'positive')
        ->and(fn () => weighIn($batch, '31.555', 9))->toThrow(DomainException::class, 'positive')
        ->and(fn () => weighIn($batch, '9999', 9))->toThrow(DomainException::class, 'plausible')
        ->and(fn () => weighIn($batch, '30', 9, 101))->toThrow(DomainException::class, 'between 1 and the 100')
        ->and(fn () => weighIn($batch, '30', 9, 0))->toThrow(DomainException::class, 'between 1 and the 100')
        ->and(fn () => weighIn($batch, '30', 25))->toThrow(DomainException::class, 'between the batch start and today')
        ->and(fn () => $record($batch, now()->addDay(), 10, '40'))->toThrow(DomainException::class, 'between the batch start and today')
        ->and(fn () => $ok->fresh()->update(['average_weight_kg' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $ok->fresh()->delete())->toThrow(LogicException::class);

    app(VoidBatchWeighIn::class)($ok, 'Scale fault');
    expect($ok->fresh()->isVoided())->toBeTrue()
        ->and(fn () => app(VoidBatchWeighIn::class)($ok->fresh(), 'again'))->toThrow(DomainException::class, 'already voided')
        ->and(fn () => app(VoidBatchWeighIn::class)(weighIn($batch, '33', 10), ' '))->toThrow(DomainException::class, 'reason')
        ->and(weighIn($batch, '34', 8)->exists)->toBeTrue();
});

it('limits a sample to the pigs present on the day and replays a retried weigh-in', function () {
    $batch = openBatch(['count' => 10, 'started_on' => now()->subDays(5)->startOfDay()]);
    app(AddPigsToBatch::class)($batch, 40, now()->subDay()->startOfDay());
    $record = app(RecordBatchWeighIn::class);

    expect(fn () => $record($batch, now()->subDays(3)->startOfDay(), 11, '30'))->toThrow(DomainException::class, 'between 1 and the 10')
        ->and($record($batch, now()->startOfDay(), 40, '35', null, null, 'dev5-w1')->sample_size)->toBe(40)
        ->and($record($batch, now()->startOfDay(), 40, '35', null, null, 'dev5-w1')->is(BatchWeighIn::first()))->toBeTrue()
        ->and(BatchWeighIn::count())->toBe(1)
        ->and(fn () => $record(openBatch(['name' => 'Other']), now()->startOfDay(), 5, '30', null, null, 'dev5-w1'))->toThrow(DomainException::class, 'different batch');
});

it('records feed for a batch or an animal, with an exact snapshot cost', function () {
    $batch = openBatch();
    $animal = register();

    $a = feed($batch, '12.34', 0, 18550);
    $b = app(RecordFeedConsumption::class)($animal, growerFeed(), now(), '2.50', ['cost_per_kg_minor' => 333]);
    $half = feed($batch, '0.50', 0, 333);
    $free = feed($batch, '5.00');

    expect($a->cost_minor)->toBe(228907)
        ->and($a->quantity_kg)->toBe('12.34')
        ->and($b->cost_minor)->toBe(833)       // 832.5 rounds half up
        ->and($b->animal->is($animal))->toBeTrue()->and($b->batch)->toBeNull()
        ->and($half->cost_minor)->toBe(167)     // 166.5 rounds half up
        ->and($free->cost_minor)->toBeNull()
        ->and(fn () => $a->update(['quantity_kg' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $a->delete())->toThrow(LogicException::class);
});

it('validates feed records', function () {
    $batch = openBatch(['started_on' => now()->subDays(5)->startOfDay()]);
    $animal = register(['birth_date' => now()->subDays(10)->toDateString()]);
    $give = app(RecordFeedConsumption::class);
    $off = FeedType::create(['code' => 'OFF', 'name' => 'Off']);
    $off->update(['is_active' => false]);

    expect(fn () => feed($batch, '0'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => feed($batch, '1.234'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => feed($batch, '-3'))->toThrow(DomainException::class, 'positive')
        ->and(fn () => feed($batch, '5', 0, -1))->toThrow(DomainException::class, 'negative')
        ->and(fn () => feed($batch, '5', 6))->toThrow(DomainException::class, 'had not started')
        ->and(fn () => $give($batch, growerFeed(), now()->addDay(), '5'))->toThrow(DomainException::class, 'future')
        ->and(fn () => $give($batch, $off, now(), '5'))->toThrow(DomainException::class, 'active feed type')
        ->and(fn () => $give($animal, growerFeed(), now()->subDays(400), '5'))->toThrow(DomainException::class, 'not active, or was not born');

    app(RecordMortality::class)($animal, now(), lookup(LookupCategory::MortalityCause, 'unknown'));
    expect(fn () => $give($animal, growerFeed(), now(), '5'))->toThrow(DomainException::class, 'not active');
});

it('voids feed records, replays retries and excludes voided feed from totals', function () {
    $batch = openBatch();
    $record = feed($batch, '100', 0, 10000, ['idempotency_key' => 'dev5-f1']);

    expect(feed($batch, '100', 0, 10000, ['idempotency_key' => 'dev5-f1'])->is($record))->toBeTrue()
        ->and(FeedConsumptionRecord::count())->toBe(1)
        ->and(fn () => feed(openBatch(['name' => 'Other']), '100', 0, 10000, ['idempotency_key' => 'dev5-f1']))->toThrow(DomainException::class, 'something else');

    app(VoidFeedConsumption::class)($record, 'Typo');
    $perf = app(GetBatchPerformance::class)($batch);

    expect($perf['feed_kg'])->toBe('0.00')->and($perf['feed_cost_minor'])->toBe(0)
        ->and(fn () => app(VoidFeedConsumption::class)($record->fresh(), 'again'))->toThrow(DomainException::class, 'already voided')
        ->and(fn () => app(VoidFeedConsumption::class)(feed($batch, '1'), ' '))->toThrow(DomainException::class, 'reason');
});

it('attributes other costs to a batch and voids wrong ones', function () {
    $batch = openBatch(['started_on' => now()->subDays(5)->startOfDay()]);
    $cost = app(RecordProductionCost::class)($batch, now()->subDay(), ProductionCostCategory::Labour, 2000000, 'Weekly wages');

    expect($cost->amount_minor)->toBe(2000000)
        ->and(fn () => app(RecordProductionCost::class)($batch, now(), ProductionCostCategory::Other, 0, 'Nothing'))->toThrow(DomainException::class, 'positive amount')
        ->and(fn () => app(RecordProductionCost::class)($batch, now(), ProductionCostCategory::Other, 5, ' '))->toThrow(DomainException::class, 'description')
        ->and(fn () => app(RecordProductionCost::class)($batch, now()->subDays(9), ProductionCostCategory::Other, 5, 'Early'))->toThrow(DomainException::class, 'dated between')
        ->and(fn () => $cost->fresh()->update(['amount_minor' => 1]))->toThrow(LogicException::class);

    app(VoidProductionCost::class)($cost, 'Duplicate');
    expect(app(GetBatchPerformance::class)($batch)['other_cost_minor'])->toBe(0)
        ->and(fn () => app(VoidProductionCost::class)($cost->fresh(), 'again'))->toThrow(DomainException::class, 'already voided');
});

it('calculates ADG, gain and FCR from the first and latest valid weigh-ins', function () {
    $perf = app(GetBatchPerformance::class)(scenario());

    expect($perf)->toMatchArray([
        'heads' => 98, 'placed' => 100, 'mortality' => 2, 'mortality_percent' => '2.00',
        'period_days' => 30, 'adg_kg' => '1.000',          // (50 - 20) / 30
        'gain_kg' => '2940.00',                              // 30 kg x 98 pigs alive
        'period_feed_kg' => '7350.00', 'fcr' => '2.50',     // 7350 / 2940
        'latest_weight_kg' => '50.00', 'days_on_feed' => 30,
    ]);
});

it('uses only valid weigh-ins for ADG', function () {
    $batch = scenario();
    $bogus = weighIn($batch, '44.00', 5, 50);

    expect(app(GetBatchPerformance::class)($batch)['adg_kg'])->toBe('1.000');   // latest valid is still today (50 kg)

    app(VoidBatchWeighIn::class)(BatchWeighIn::where('weighed_on', now()->startOfDay())->firstOrFail(), 'Wrong batch weighed');
    $after = app(GetBatchPerformance::class)($batch);

    // Latest valid weigh-in is now day 25 (44 kg): (44 - 20) / 25 days.
    expect($after['adg_kg'])->toBe('0.960')->and($after['latest_weight_kg'])->toBe('44.00')->and($bogus->isVoided())->toBeFalse();
});

it('measures FCR over a chosen feed and weight-gain period', function () {
    $batch = scenario();
    $perf = app(GetBatchPerformance::class)($batch, now()->subDays(15), now());

    // Feed after day 15 up to today: 2000 + 2350 kg; gain (50 - 35) x 98 = 1470 kg.
    expect($perf)->toMatchArray(['period_start' => now()->subDays(15)->toDateString(), 'period_days' => 15, 'adg_kg' => '1.000', 'gain_kg' => '1470.00', 'period_feed_kg' => '4350.00', 'fcr' => '2.96'])
        ->and(fn () => app(GetBatchPerformance::class)($batch, now()->subDays(3), now()))->toThrow(DomainException::class, 'no valid weigh-in')
        ->and(fn () => app(GetBatchPerformance::class)($batch, now(), now()->subDays(15)))->toThrow(DomainException::class, 'end after it starts');
});

it('calculates cost per pig and per kg gained', function () {
    $perf = app(GetBatchPerformance::class)(scenario());

    // entry 100 x 500,000 + feed 7,350 kg x 25,000 + labour 2,000,000
    expect($perf['entry_cost_minor'])->toBe(50000000)
        ->and($perf['feed_cost_minor'])->toBe(183750000)
        ->and($perf['other_cost_minor'])->toBe(2000000)
        ->and($perf['total_cost_minor'])->toBe(235750000)
        ->and($perf['cost_per_pig_minor'])->toBe(2405612)        // / 98 pigs that did not die
        ->and($perf['cost_per_kg_gain_minor'])->toBe(63180);     // (feed + other) / 2,940 kg
});

it('predicts the market date and projected weight from the measured ADG', function () {
    $batch = scenario();
    $perf = app(GetBatchPerformance::class)($batch);

    expect($perf['target_weight_kg'])->toBe('100.00')
        ->and($perf['days_to_market'])->toBe(50)                                    // 50 kg to go at 1 kg/day
        ->and($perf['expected_market_on'])->toBe(now()->addDays(50)->toDateString())
        ->and(app(GetBatchPerformance::class)->projectWeight($batch, now()->addDays(10)))->toBe('60.00')
        ->and(app(GetBatchPerformance::class)->projectWeight($batch, now()->subDay()))->toBeNull();

    $batch->update(['target_weight_kg' => '80']);
    expect(app(GetBatchPerformance::class)($batch->fresh())['days_to_market'])->toBe(30);

    app(ResolveSettings::class)->set('production.target_market_weight_kg', 120);
    $batch->update(['target_weight_kg' => null]);
    expect(app(GetBatchPerformance::class)($batch->fresh())['days_to_market'])->toBe(70);
});

it('rounds the days to market up and handles batches already at weight or not growing', function () {
    $slow = openBatch(['started_on' => now()->subDays(3)->startOfDay(), 'average_weight_kg' => '10.00']);
    weighIn($slow, '13.00', 0, 50);   // 1 kg/day: 87 kg to go
    expect(app(GetBatchPerformance::class)($slow)['days_to_market'])->toBe(87);

    $odd = openBatch(['name' => 'Odd', 'started_on' => now()->subDays(3)->startOfDay(), 'average_weight_kg' => '10.00']);
    weighIn($odd, '12.00', 0, 50);    // 0.667 kg/day: 88 / 0.6667 = 132 days exactly when rounded up
    expect(app(GetBatchPerformance::class)($odd)['days_to_market'])->toBe(132);

    $done = openBatch(['name' => 'Heavy', 'started_on' => now()->subDays(3)->startOfDay(), 'average_weight_kg' => '98.00']);
    weighIn($done, '103.00', 0, 50);
    expect(app(GetBatchPerformance::class)($done)['days_to_market'])->toBe(0);

    $flat = openBatch(['name' => 'Flat', 'started_on' => now()->subDays(3)->startOfDay(), 'average_weight_kg' => '30.00']);
    weighIn($flat, '30.00', 0, 50);
    expect(app(GetBatchPerformance::class)($flat)['expected_market_on'])->toBeNull();

    $one = openBatch(['name' => 'One', 'average_weight_kg' => '30.00']);
    $p = app(GetBatchPerformance::class)($one);
    expect($p['adg_kg'] ?? null)->toBeNull()->and($p['fcr'] ?? null)->toBeNull()->and($p['expected_market_on'])->toBeNull()
        ->and($p['cost_per_kg_gain_minor'])->toBeNull();
});

it('calculates an animal\'s growth from valid weights only', function () {
    $animal = register(['birth_date' => now()->subDays(100)->toDateString()]);
    $weigh = app(RecordWeight::class);
    $weigh($animal, '30', now()->subDays(20));
    $bad = $weigh($animal, '90', now()->subDays(10));
    $weigh($animal, '50', now());
    app(VoidWeight::class)($bad, 'Wrong animal');
    app(RecordFeedConsumption::class)($animal, growerFeed(), now()->subDays(5), '40');
    app(RecordFeedConsumption::class)($animal, growerFeed(), now()->subDays(25), '99'); // before the first weight: excluded

    $growth = app(GetAnimalGrowth::class)($animal);

    expect($growth)->toMatchArray(['weights' => 2, 'days' => 20, 'gain_kg' => '20.00', 'adg_kg' => '1.000', 'feed_kg' => '40.00', 'fcr' => '2.00'])
        ->and(app(GetAnimalGrowth::class)(register())['adg_kg'])->toBeNull();
});

it('summarises active batches only', function () {
    scenario();
    $done = openBatch(['name' => 'Gone', 'count' => 2]);
    app(RemovePigsFromBatch::class)($done, BatchEventType::Sale, 2, now()->startOfDay());

    $summary = app(GetProductionSummary::class)();

    expect($summary)->toHaveCount(1)
        ->and($summary->first()['batch']->name)->toBe('Grower batch')
        ->and($summary->first()['performance']['heads'])->toBe(98);
});

it('applies production permission defaults', function () {
    $batch = openBatch();

    expect(farmWorker()->can('create', ProductionBatch::class))->toBeTrue()
        ->and(farmWorker()->can('approve', ProductionBatch::class))->toBeFalse()
        ->and(userWithRole('Farm Manager')->can('approve', ProductionBatch::class))->toBeTrue()
        ->and(userWithRole('Feed Mill Manager')->can('viewAny', ProductionBatch::class))->toBeTrue()
        ->and(userWithRole('Feed Mill Manager')->can('create', ProductionBatch::class))->toBeFalse()
        ->and(userWithRole('Accountant')->can('export', ProductionBatch::class))->toBeTrue()
        ->and(userWithRole('Semen Laboratory Manager')->can('viewAny', ProductionBatch::class))->toBeFalse()
        ->and(owner()->can('delete', $batch))->toBeFalse()
        ->and(owner()->can('delete', growerFeed()))->toBeTrue();

    feed($batch, '5');
    expect(owner()->can('delete', growerFeed()))->toBeFalse()
        ->and(owner()->can('delete', FeedConsumptionRecord::first()))->toBeFalse();
});
