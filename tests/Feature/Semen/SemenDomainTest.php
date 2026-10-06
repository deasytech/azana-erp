<?php

use App\Domain\Breeding\Actions\RecordService;
use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Farm\Models\Farm;
use App\Domain\Farm\Models\PriceList;
use App\Domain\Farm\Models\PriceListItem;
use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Health\Actions\StartQuarantine;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Semen\Actions\AssertSemenBatchSellable;
use App\Domain\Semen\Actions\ExpireSemenBatches;
use App\Domain\Semen\Actions\GetSemenPrice;
use App\Domain\Semen\Actions\GetSemenProduction;
use App\Domain\Semen\Actions\GetSemenStock;
use App\Domain\Semen\Actions\ManageSemenBatch;
use App\Domain\Semen\Actions\ManageSemenBoar;
use App\Domain\Semen\Actions\ProcessSemenBatch;
use App\Domain\Semen\Actions\RecordSemenCollection;
use App\Domain\Semen\Actions\RecordSemenQc;
use App\Domain\Semen\Actions\ReleaseSemenBatch;
use App\Domain\Semen\Models\SemenBatch;
use App\Domain\Semen\Models\SemenBoar;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\AnimalStatus;
use App\Enums\InventoryTransactionType as T;
use App\Enums\LookupCategory;
use App\Enums\QuarantineType;
use App\Enums\SemenBatchStatus as Status;
use App\Enums\SemenBoarStatus;
use App\Enums\ServiceMethod;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

describe('boars in the programme', function () {
    it('lets only an active boar join, once', function () {
        $boar = register(['sex' => 'male', 'category_id' => categoryId('boar')]);

        expect(app(ManageSemenBoar::class)->enrol($boar)->status)->toBe(SemenBoarStatus::Active);
        expect(fn () => app(ManageSemenBoar::class)->enrol($boar))->toThrow(DomainException::class, 'already in the semen programme');
        expect(fn () => app(ManageSemenBoar::class)->enrol(register()))->toThrow(DomainException::class, 'active boar');
        expect(fn () => app(ManageSemenBoar::class)->enrol(register(['sex' => 'male', 'category_id' => categoryId('boar')]), ['status' => 'asleep']))->toThrow(DomainException::class, 'active, resting or retired');
    });

    it('keeps each boar\'s own limits and status', function () {
        $boar = semenBoar(programme: ['min_interval_days' => 2, 'target_doses_per_week' => 60]);
        $programme = SemenBoar::firstWhere('animal_id', $boar->id);

        app(ManageSemenBoar::class)->update($programme, ['status' => 'resting']);

        expect($programme->fresh()->status)->toBe(SemenBoarStatus::Resting)->and($programme->min_interval_days)->toBe(2);
        expect(fn () => app(ManageSemenBoar::class)->update($programme, ['min_interval_days' => -1]))->toThrow(DomainException::class, 'whole numbers');
    });
});

describe('collections', function () {
    it('creates a numbered batch for every collection', function () {
        $day = now()->format('Ymd');
        $first = collectSemen(semenBoar());
        $second = collectSemen(semenBoar());          // another boar, same breed and day
        $landrace = collectSemen(semenBoar('LR'));

        expect($first->number)->toBe("IPA-SM-DUR-{$day}-001")->and($second->number)->toBe("IPA-SM-DUR-{$day}-002")->and($landrace->number)->toBe("IPA-SM-LR-{$day}-001")
            ->and($first->status)->toBe(Status::PendingQc)
            ->and($first->expiry_date->toDateString())->toBe(now()->addDays(4)->toDateString())
            ->and($first->breed->code)->toBe('DUR')
            ->and($first->collection->volume_ml)->toBe('250.0')
            ->and($first->boar->animal_number)->toStartWith('IPA-');
    });

    it('needs an active, collecting, healthy boar', function () {
        $collect = app(RecordSemenCollection::class);
        $boar = semenBoar();

        expect(fn () => $collect(register(['sex' => 'male', 'category_id' => categoryId('boar')]), now(), '100'))->toThrow(DomainException::class, 'semen programme');

        $programme = SemenBoar::firstWhere('animal_id', $boar->id);
        $programme->update(['status' => SemenBoarStatus::Resting]);
        expect(fn () => $collect($boar, now(), '100'))->toThrow(DomainException::class, 'not being collected from');
        $programme->update(['status' => SemenBoarStatus::Active]);

        app(StartQuarantine::class)($boar, QuarantineType::Quarantine, now()->startOfDay(), 'Suspected infection');
        expect(fn () => $collect($boar, now(), '100'))->toThrow(DomainException::class, 'quarantine');
        expect(SemenBatch::count())->toBe(0);
    });

    it('checks the volume, pH and date', function () {
        $collect = app(RecordSemenCollection::class);
        $boar = semenBoar();

        expect(fn () => $collect($boar, now(), '0'))->toThrow(DomainException::class, 'between 0.1 and 1000');
        expect(fn () => $collect($boar, now(), '1001'))->toThrow(DomainException::class, 'between 0.1 and 1000');
        expect(fn () => $collect($boar, now(), '10.55'))->toThrow(DomainException::class, '1 decimal');
        expect(fn () => $collect($boar, now(), '100', ['ph' => '15']))->toThrow(DomainException::class, 'pH');
        expect(fn () => $collect($boar, now()->addDay(), '100'))->toThrow(DomainException::class, 'future');
    });

    it('makes a boar rest between collections', function () {
        $boar = semenBoar();
        collectSemen($boar, 3);

        expect(fn () => collectSemen($boar, 0))->toThrow(DomainException::class, 'needs 4 days between collections');
        expect(SemenBatch::count())->toBe(1);
    });

    it('can be repeated safely with an idempotency key', function () {
        $boar = semenBoar();
        $a = app(RecordSemenCollection::class)($boar, now(), '250', [], null, 'key-1');
        $b = app(RecordSemenCollection::class)($boar, now(), '250', [], null, 'key-1');

        expect($b->id)->toBe($a->id)->and(SemenBatch::count())->toBe(1);
        expect(fn () => app(RecordSemenCollection::class)(semenBoar(), now(), '250', [], null, 'key-1'))->toThrow(DomainException::class, 'idempotency');
    });

    it('allows a rested boar, and a boar with a shorter interval of its own', function () {
        $boar = semenBoar();
        collectSemen($boar, 4);
        expect(collectSemen($boar, 0))->toBeInstanceOf(SemenBatch::class);

        $quick = semenBoar(programme: ['min_interval_days' => 1]);
        collectSemen($quick, 1);
        expect(collectSemen($quick, 0))->toBeInstanceOf(SemenBatch::class);
    });
});

describe('laboratory QC', function () {
    it('passes a batch that meets the standards', function () {
        $batch = collectSemen(semenBoar());

        $qc = app(RecordSemenQc::class)($batch, '80', '300', '10', 'Good', userWithRole('Semen Laboratory Manager'));

        expect($qc->passed)->toBeTrue()->and($qc->failed_because)->toBeNull()->and($batch->fresh()->status)->toBe(Status::Passed);
    });

    it('fails a batch that misses a standard, and says why', function (string $motility, string $concentration, string $abnormal, string $reason) {
        $batch = collectSemen(semenBoar());

        $qc = app(RecordSemenQc::class)($batch, $motility, $concentration, $abnormal);

        expect($qc->passed)->toBeFalse()->and($qc->failed_because)->toContain($reason)->and($batch->fresh()->status)->toBe(Status::Failed);
    })->with([
        'low motility' => ['60', '300', '10', 'Motility 60'],
        'low concentration' => ['80', '150', '10', 'Concentration 150'],
        'many abnormal' => ['80', '300', '25', 'Abnormal forms 25'],
    ]);

    it('judges against the standards set in the farm settings', function () {
        app(ResolveSettings::class)->set('semen.min_motility_percent', 50);

        expect(passSemenQc(collectSemen(semenBoar()))->status)->toBe(Status::Passed);
        $weak = collectSemen(semenBoar());
        app(RecordSemenQc::class)($weak, '55', '300', '10');
        expect($weak->fresh()->status)->toBe(Status::Passed);
    });

    it('is recorded once and never edited', function () {
        $batch = collectSemen(semenBoar());
        $qc = app(RecordSemenQc::class)($batch, '80', '300', '10');

        expect(fn () => app(RecordSemenQc::class)($batch, '90', '300', '5'))->toThrow(DomainException::class, 'pending QC');
        expect(fn () => $qc->update(['passed' => false]))->toThrow(LogicException::class);
    });

    it('checks the figures', function () {
        $batch = collectSemen(semenBoar());

        expect(fn () => app(RecordSemenQc::class)($batch, '101', '300', '10'))->toThrow(DomainException::class, 'percentages');
        expect(fn () => app(RecordSemenQc::class)($batch, '80', 'lots', '10'))->toThrow(DomainException::class, 'concentration');
        expect($batch->fresh()->status)->toBe(Status::PendingQc);
    });
});

describe('processing and release', function () {
    it('limits the doses to what the ejaculate can yield', function () {
        $batch = passSemenQc(collectSemen(semenBoar()));   // 250 ml x 300 million/ml x 80% = 60,000 million; 2,500 million per dose

        expect(app(ProcessSemenBatch::class)->maxDoses($batch->load('collection')))->toBe(24);
        expect(fn () => app(ProcessSemenBatch::class)($batch, 25, '80'))->toThrow(DomainException::class, 'between 1 and 24');
        expect(fn () => app(ProcessSemenBatch::class)($batch, 0, '80'))->toThrow(DomainException::class, 'between 1 and 24');
        expect(fn () => app(ProcessSemenBatch::class)($batch, 24, '0'))->toThrow(DomainException::class, 'dose volume');

        $done = app(ProcessSemenBatch::class)($batch, 24, '80', 'BTS');
        expect($done->doses_produced)->toBe(24)->and($done->diluent)->toBe('BTS');
    });

    it('says so, rather than dividing by zero, when sperm per dose is not set', function () {
        $batch = passSemenQc(collectSemen(semenBoar()));
        app(ResolveSettings::class)->set('semen.sperm_per_dose_million', 0);

        expect(fn () => app(ProcessSemenBatch::class)($batch, 5, '80'))->toThrow(DomainException::class, 'sperm per dose');
        expect(fn () => app(ProcessSemenBatch::class)->maxDoses($batch->load('collection')))->toThrow(DomainException::class, 'sperm per dose');
        expect($batch->fresh()->doses_produced)->toBeNull();
    });

    it('cannot process a batch that has not passed', function () {
        expect(fn () => app(ProcessSemenBatch::class)(collectSemen(semenBoar()), 5, '80'))->toThrow(DomainException::class, 'passed QC');
    });

    it('puts the doses into stock by breed, batch and expiry when released', function () {
        app(ResolveSettings::class)->set('semen.cost_per_dose_minor', 5000);
        $batch = releasedSemen();

        $item = InventoryItem::firstWhere('code', 'SEMEN-DUR');
        $stock = app(GetStockLevels::class)(itemId: $item->id)->sole();

        expect($batch->status)->toBe(Status::Released)->and($batch->released_at)->not->toBeNull()
            ->and($stock->on_hand)->toBe('24.000')->and($stock->value_minor)->toBe(120000)
            ->and($stock->batch->batch_number)->toBe($batch->number)
            ->and($stock->batch->expiry_date->toDateString())->toBe($batch->expiry_date->toDateString())
            ->and($batch->inventory_batch_id)->toBe($stock->inventory_batch_id)
            ->and($batch->isSellable())->toBeTrue();
        expect(InventoryTransaction::sole()->source_type)->toBe('semen_batch');
    });

    it('needs approval from someone other than the analyst', function () {
        $analyst = userWithRole('Semen Laboratory Manager');
        $batch = passSemenQc(collectSemen(semenBoar()), $analyst);
        app(ProcessSemenBatch::class)($batch, 24, '80');
        $release = app(ReleaseSemenBatch::class);

        expect(fn () => $release($batch, farmWorker(), store('SEMEN')))->toThrow(DomainException::class, 'not authorised');
        expect(fn () => $release($batch, $analyst, store('SEMEN')))->toThrow(DomainException::class, 'someone other than');
        expect($batch->fresh()->status)->toBe(Status::Passed)->and(InventoryTransaction::count())->toBe(0);

        expect($release($batch, userWithRole('Farm Manager'), store('SEMEN'))->status)->toBe(Status::Released);
        expect(fn () => $release($batch, userWithRole('Farm Manager'), store('SEMEN')))->toThrow(DomainException::class, 'only a batch that passed QC');
    });

    it('needs the batch to be processed, and a stock item for the breed', function () {
        $batch = passSemenQc(collectSemen(semenBoar()));
        $manager = userWithRole('Farm Manager');

        expect(fn () => app(ReleaseSemenBatch::class)($batch, $manager, store('SEMEN')))->toThrow(DomainException::class, 'how many doses');

        app(ProcessSemenBatch::class)($batch, 10, '80');
        InventoryItem::firstWhere('code', 'SEMEN-DUR')->update(['is_active' => false]);
        expect(fn () => app(ReleaseSemenBatch::class)($batch, $manager, store('SEMEN')))->toThrow(DomainException::class, 'semen stock item');

        $unbred = register(['sex' => 'male', 'category_id' => categoryId('boar')]);
        app(ManageSemenBoar::class)->enrol($unbred);
        $noBreed = passSemenQc(collectSemen($unbred));
        app(ProcessSemenBatch::class)($noBreed, 10, '80');
        expect($noBreed->breed_id)->toBeNull();
        expect(fn () => app(ReleaseSemenBatch::class)($noBreed, $manager, store('SEMEN')))->toThrow(DomainException::class, 'breed');
    });
});

describe('saleability', function () {
    it('never lets a failed batch be released or sold', function () {
        $batch = collectSemen(semenBoar());
        app(RecordSemenQc::class)($batch, '40', '300', '10');

        expect(fn () => app(ProcessSemenBatch::class)($batch, 5, '80'))->toThrow(DomainException::class, 'passed QC');
        expect(fn () => app(ReleaseSemenBatch::class)($batch, userWithRole('Farm Manager'), store('SEMEN')))->toThrow(DomainException::class, 'only a batch that passed QC');
        expect(fn () => app(AssertSemenBatchSellable::class)($batch))->toThrow(DomainException::class, 'cannot be sold or used');
        expect($batch->fresh()->isSellable())->toBeFalse()->and(app(GetStockLevels::class)())->toBeEmpty();
    });

    it('is decided by status for every batch that is not released', function (callable $make) {
        expect(fn () => app(AssertSemenBatchSellable::class)($make()))->toThrow(DomainException::class);
    })->with([
        'pending QC' => [fn () => collectSemen(semenBoar())],
        'passed' => [fn () => passSemenQc(collectSemen(semenBoar()))],
        'processed' => [fn () => processedSemen()],
    ]);

    it('accepts a released batch, until it expires', function () {
        $batch = releasedSemen();

        expect(app(AssertSemenBatchSellable::class)($batch)->id)->toBe($batch->id);

        $this->travel(5)->days();
        expect(fn () => app(AssertSemenBatchSellable::class)($batch))->toThrow(DomainException::class, 'expired')
            ->and($batch->fresh()->isSellable())->toBeFalse();
    });

    it('stops inventory from issuing doses of a batch that is not sellable', function () {
        $batch = releasedSemen();
        app(ManageSemenBatch::class)->quarantine($batch, 'Contamination suspected');

        expect(fn () => issueStock(InventoryItem::firstWhere('code', 'SEMEN-DUR'), '1', store('SEMEN'), T::Sale, ['batch' => $batch->inventory_batch_id]))
            ->toThrow(DomainException::class, 'not active');
    });
});

describe('quarantine, destruction and expiry', function () {
    it('quarantines a released batch and clears it with approval', function () {
        $batch = releasedSemen();
        $manage = app(ManageSemenBatch::class);

        $manage->quarantine($batch, 'Recall');
        expect($batch->fresh()->status)->toBe(Status::Quarantined)->and($batch->fresh()->isSellable())->toBeFalse()->and($batch->fresh()->quarantined_from)->toBe('released');

        expect(fn () => $manage->clearQuarantine($batch, farmWorker()))->toThrow(DomainException::class, 'not authorised');
        $manage->clearQuarantine($batch, userWithRole('Farm Manager'), 'Retested fine');

        expect($batch->fresh()->status)->toBe(Status::Released)->and($batch->fresh()->isSellable())->toBeTrue();
        expect(fn () => $manage->clearQuarantine($batch, userWithRole('Farm Manager')))->toThrow(DomainException::class, 'not in quarantine');
    });

    it('only quarantines a batch that is open for it, with a reason', function () {
        $failed = collectSemen(semenBoar());
        app(RecordSemenQc::class)($failed, '10', '300', '10');

        expect(fn () => app(ManageSemenBatch::class)->quarantine($failed, 'x'))->toThrow(DomainException::class, 'cannot be quarantined');
        expect(fn () => app(ManageSemenBatch::class)->quarantine(releasedSemen(), ' '))->toThrow(DomainException::class, 'reason');
    });

    it('destroys a batch, writing off the doses left', function () {
        $batch = releasedSemen();
        issueStock(InventoryItem::firstWhere('code', 'SEMEN-DUR'), '6', store('SEMEN'), T::Consumption, ['batch' => $batch->inventory_batch_id]);

        app(ManageSemenBatch::class)->destroy($batch, 'Cold chain failed');

        $levels = app(GetStockLevels::class);
        expect($batch->fresh()->status)->toBe(Status::Destroyed)->and($levels())->toBeEmpty()
            ->and(InventoryTransaction::where('type', T::Wastage)->sole()->quantity)->toBe('-18.000');
        expect(fn () => app(ManageSemenBatch::class)->destroy($batch, 'Again'))->toThrow(DomainException::class, 'already Destroyed');
    });

    it('destroys a batch that was never released, and one in quarantine', function () {
        expect(app(ManageSemenBatch::class)->destroy(collectSemen(semenBoar()), 'Spilled')->status)->toBe(Status::Destroyed);

        $quarantined = releasedSemen();
        app(ManageSemenBatch::class)->quarantine($quarantined, 'Recall');
        app(ManageSemenBatch::class)->destroy($quarantined, 'Recall confirmed');

        expect($quarantined->fresh()->status)->toBe(Status::Destroyed)->and(app(GetStockLevels::class)())->toBeEmpty();
    });

    it('expires batches past their date and writes off what is left', function () {
        $stock = releasedSemen();
        $unprocessed = collectSemen(semenBoar());
        $fresh = collectSemen(semenBoar(), 0);

        $this->travel(5)->days();
        $expired = app(ExpireSemenBatches::class)();

        expect($expired)->toBe(3)
            ->and($stock->fresh()->status)->toBe(Status::Expired)->and($unprocessed->fresh()->status)->toBe(Status::Expired)
            ->and(app(GetStockLevels::class)())->toBeEmpty()
            ->and(InventoryTransaction::where('type', T::Wastage)->sole()->quantity)->toBe('-24.000');
        expect(app(ExpireSemenBatches::class)())->toBe(0);
    });
});

describe('artificial insemination with a released batch', function () {
    it('uses a dose from stock and records the boar as sire', function () {
        $batch = releasedSemen();
        $sow = register();

        $service = app(RecordService::class)($sow, ServiceMethod::ArtificialInsemination, now()->startOfDay(), semenBatchId: $batch->id, semenLocationId: store('SEMEN')->id);

        expect($service->semen_batch_id)->toBe($batch->id)->and($service->boar_id)->toBe($batch->animal_id)
            ->and(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('23.000');
        $dose = InventoryTransaction::where('type', T::Consumption)->sole();
        expect($dose->source_type)->toBe('breeding_service')->and($dose->source_id)->toBe($service->id)->and($dose->inventory_batch_id)->toBe($batch->inventory_batch_id);
    });

    it('works even after the donor boar has left the farm', function () {
        $boar = semenBoar();
        $batch = releasedSemen($boar);
        $boar->update(['status' => AnimalStatus::Sold]);

        $service = app(RecordService::class)(register(), ServiceMethod::ArtificialInsemination, now()->startOfDay(), semenBatchId: $batch->id, semenLocationId: store('SEMEN')->id);

        expect($service->boar_id)->toBe($boar->id);
    });

    it('refuses a batch that is not sellable, the wrong boar, natural mating or no store, and uses no stock', function () {
        $record = app(RecordService::class);
        $released = releasedSemen();
        $pending = collectSemen(semenBoar());

        expect(fn () => $record(register(), ServiceMethod::ArtificialInsemination, now(), semenBatchId: $pending->id, semenLocationId: store('SEMEN')->id))->toThrow(DomainException::class, 'cannot be sold or used');
        expect(fn () => $record(register(), ServiceMethod::ArtificialInsemination, now(), boar()->id, semenBatchId: $released->id, semenLocationId: store('SEMEN')->id))->toThrow(DomainException::class, 'not from the chosen boar');
        expect(fn () => $record(register(), ServiceMethod::Natural, now(), semenBatchId: $released->id, semenLocationId: store('SEMEN')->id))->toThrow(DomainException::class, 'artificial insemination');
        expect(fn () => $record(register(), ServiceMethod::ArtificialInsemination, now(), semenBatchId: $released->id))->toThrow(DomainException::class, 'which store');

        expect(app(GetStockLevels::class)->total(InventoryItem::firstWhere('code', 'SEMEN-DUR')->id))->toBe('24.000')
            ->and(BreedingService::count())->toBe(0);
    });
});

describe('reports and prices', function () {
    it('shows production per boar against targets', function () {
        $boar = semenBoar();
        $batch = passSemenQc(collectSemen($boar, 4));
        app(ProcessSemenBatch::class)($batch, 20, '80');
        $failed = collectSemen($boar, 0);
        app(RecordSemenQc::class)($failed, '10', '300', '10');
        $idle = semenBoar('LR');

        $report = app(GetSemenProduction::class)(now()->subDays(6), now());   // 7 days = 1 week, target 40 doses per boar

        $row = collect($report['rows'])->firstWhere('animal_id', $boar->id);
        expect($row['collections'])->toBe(2)->and($row['passed'])->toBe(1)->and($row['failed'])->toBe(1)->and($row['doses'])->toBe(20)
            ->and($row['target_doses'])->toBe(40)->and($row['attainment_percent'])->toBe('50.0')
            ->and($report['totals']['pass_rate_percent'])->toBe('50.0');
        expect(collect($report['rows'])->firstWhere('animal_id', $idle->id)['collections'])->toBe(0);
    });

    it('counts a batch as passed or failed by what the laboratory decided, whatever became of it since', function () {
        $boar = semenBoar();
        $gone = releasedSemen($boar);
        $other = semenBoar();
        $failed = collectSemen($other);
        app(RecordSemenQc::class)($failed, '10', '300', '10');
        app(ManageSemenBatch::class)->destroy($failed, 'Discarded');
        $unchecked = collectSemen(semenBoar());

        app(ManageSemenBatch::class)->destroy($gone, 'Cold chain failed');    // passed QC, then destroyed
        $rows = collect(app(GetSemenProduction::class)(now()->subDays(6), now())['rows']);

        expect($rows->firstWhere('animal_id', $boar->id))->toMatchArray(['passed' => 1, 'failed' => 0])
            ->and($rows->firstWhere('animal_id', $other->id))->toMatchArray(['passed' => 0, 'failed' => 1])
            ->and($rows->firstWhere('animal_id', $unchecked->animal_id))->toMatchArray(['collections' => 1, 'passed' => 0, 'failed' => 0]);
    });

    it('uses a boar\'s own target and ignores a resting boar\'s', function () {
        $own = semenBoar(programme: ['target_doses_per_week' => 100]);
        $resting = semenBoar(programme: ['status' => 'resting']);

        $rows = collect(app(GetSemenProduction::class)(now()->subDays(6), now())['rows']);

        expect($rows->firstWhere('animal_id', $own->id)['target_doses'])->toBe(100)->and($rows->firstWhere('animal_id', $resting->id))->toBeNull();
        app(ResolveSettings::class)->set('semen.target_doses_per_week', 10);
        expect(collect(app(GetSemenProduction::class)(now()->subDays(13), now())['rows'])->firstWhere('animal_id', $own->id)['target_doses'])->toBe(200);
    });

    it('lists semen stock by breed, boar, batch and expiry', function () {
        $dur = releasedSemen(semenBoar('DUR'));
        $lr = releasedSemen(semenBoar('LR'));
        releasedSemen();    // another Duroc batch

        $rows = app(GetSemenStock::class)();

        expect($rows)->toHaveCount(3)
            ->and($rows->firstWhere('batch', $lr->number))->toMatchArray(['breed' => 'Landrace', 'doses' => '24.000', 'store' => 'Semen laboratory store', 'sellable' => true, 'status' => 'Released'])
            ->and($rows->firstWhere('batch', $dur->number)['boar'])->toBe($dur->boar->animal_number);
    });

    it('looks up the dose price from the active price list in force', function () {
        $item = InventoryItem::firstWhere('code', 'SEMEN-DUR');
        $farm = Farm::first();
        $category = lookup(LookupCategory::PriceCategory, 'semen');
        $list = fn (string $code, array $over) => PriceList::create($over + ['farm_id' => $farm->id, 'category_id' => $category, 'code' => $code, 'name' => $code, 'currency_code' => 'NGN', 'is_active' => true]);
        $price = fn (PriceList $list, int $minor) => PriceListItem::create(['price_list_id' => $list->id, 'code' => 'DUR', 'description' => 'Duroc dose', 'unit_id' => UnitOfMeasure::firstWhere('code', 'DOSE')->id, 'unit_price_minor' => $minor, 'inventory_item_id' => $item->id]);

        expect(app(GetSemenPrice::class)($item))->toBeNull();

        $price($list('OLD', ['valid_from' => now()->subYear()]), 800000);
        $price($list('NEW', ['valid_from' => now()->subMonth()]), 1000000);
        $price($list('FUTURE', ['valid_from' => now()->addMonth()]), 2000000);
        $price($list('OFF', ['is_active' => false, 'valid_from' => now()->subDay()]), 3000000);
        $price($list('ENDED', ['valid_from' => now()->subYear(), 'valid_to' => now()->subDays(5)]), 4000000);

        expect(app(GetSemenPrice::class)($item))->toBe(['price_minor' => 1000000, 'currency' => 'NGN', 'price_list' => 'NEW'])
            ->and(app(GetSemenPrice::class)($item, now()->addMonths(2))['price_minor'])->toBe(2000000);
    });
});

it('grants semen rights by role', function () {
    $lab = userWithRole('Semen Laboratory Manager');

    expect($lab->can('semen.approve'))->toBeTrue()->and(userWithRole('Farm Manager')->can('semen.approve'))->toBeTrue()
        ->and(farmWorker()->can('semen.create'))->toBeTrue()->and(farmWorker()->can('semen.approve'))->toBeFalse()
        ->and(userWithRole('Sales Officer')->can('semen.view'))->toBeTrue()->and(userWithRole('Sales Officer')->can('semen.create'))->toBeFalse()
        ->and($lab->can('delete', collectSemen(semenBoar())))->toBeFalse();

    // Not even the owner: the owner bypass covers permission names like "semen.delete", never the policy's own rule.
    $batch = collectSemen(semenBoar());
    expect(owner()->can('delete', $batch))->toBeFalse()->and(owner()->can('deleteAny', SemenBatch::class))->toBeFalse();
});
