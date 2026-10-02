<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Feed\Actions\RecordFeedConsumption;
use App\Domain\Feed\Actions\VoidFeedConsumption;
use App\Domain\Feed\Models\FeedType;
use App\Domain\Inventory\Actions\ApproveStockAdjustment;
use App\Domain\Inventory\Actions\ApproveStockCount;
use App\Domain\Inventory\Actions\CancelStockCount;
use App\Domain\Inventory\Actions\GetExpiryAlerts;
use App\Domain\Inventory\Actions\GetReorderAlerts;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\RecordCountLine;
use App\Domain\Inventory\Actions\RejectStockAdjustment;
use App\Domain\Inventory\Actions\RejectStockCount;
use App\Domain\Inventory\Actions\RequestStockAdjustment;
use App\Domain\Inventory\Actions\StartStockCount;
use App\Domain\Inventory\Actions\SubmitStockCount;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\ApprovalStatus;
use App\Enums\InventoryCategory;
use App\Enums\InventoryTransactionType as T;
use App\Enums\StockCountStatus;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    $this->counter = userWithRole('Store Officer');
    $this->manager = userWithRole('Farm Manager');
});

/** A draft count of the main store: maize system 100 kg @ 100, counted 90 (short, "Spillage"). */
function draftCount($counter): StockCount
{
    $maize = stockItem('MAIZE');
    receiveStock($maize, '100', 100);

    $count = app(StartStockCount::class)(store(), now()->startOfDay(), null, $counter);
    app(RecordCountLine::class)($count, $maize, null, '90', 'Spillage');

    return $count;
}

it('snapshots the system quantities when a count starts and allows one count per store', function () {
    receiveStock(stockItem('MAIZE'), '100', 100);
    receiveStock(stockItem('SOYA'), '20.5', 300);

    $count = app(StartStockCount::class)(store(), now()->startOfDay(), null, $this->counter);

    expect($count->number)->toStartWith('SC-')->and($count->status)->toBe(StockCountStatus::Draft)
        ->and($count->lines->map(fn ($l) => (string) $l->system_quantity)->sort()->values()->all())->toBe(['20.500', '100.000']);
    expect(fn () => app(StartStockCount::class)(store(), now()))->toThrow(DomainException::class, 'already has a count in progress');
    expect(app(StartStockCount::class)(store('OTHER'), now()))->toBeInstanceOf(StockCount::class);
});

it('needs every line counted and every difference explained before submitting', function () {
    receiveStock(stockItem('MAIZE'), '100', 100);
    $count = app(StartStockCount::class)(store(), now()->startOfDay(), null, $this->counter);

    expect(fn () => app(SubmitStockCount::class)($count, $this->counter))->toThrow(DomainException::class, 'counted quantity');

    $line = app(RecordCountLine::class)($count, stockItem('MAIZE'), null, '90', null);
    expect((string) $line->variance_quantity)->toBe('-10.000')->and($line->variance_value_minor)->toBe(-1000);
    expect(fn () => app(SubmitStockCount::class)($count, $this->counter))->toThrow(DomainException::class, 'reason');

    app(RecordCountLine::class)($count, stockItem('MAIZE'), null, '90', 'Spillage');
    expect(app(SubmitStockCount::class)($count, $this->counter)->status)->toBe(StockCountStatus::Submitted);
    expect(fn () => app(RecordCountLine::class)($count, stockItem('MAIZE'), null, '95', 'x'))->toThrow(DomainException::class, 'not been submitted');
});

it('needs approval before a count changes any stock', function () {
    $count = draftCount($this->counter);
    app(SubmitStockCount::class)($count, $this->counter);

    expect(app(GetStockLevels::class)->total(stockItem('MAIZE')->id))->toBe('100.000')
        ->and(InventoryTransaction::where('type', T::Adjustment)->count())->toBe(0);

    // The person who submitted it, and people without approval rights, cannot approve.
    expect(fn () => app(ApproveStockCount::class)($count, $this->counter))->toThrow(DomainException::class, 'not authorised');
    expect(fn () => app(ApproveStockCount::class)($count, farmWorker()))->toThrow(DomainException::class, 'not authorised');
    expect(InventoryTransaction::where('type', T::Adjustment)->count())->toBe(0);
});

it('posts the approved variances as adjustments valued from the ledger', function () {
    $count = draftCount($this->counter);
    app(RecordCountLine::class)($count, stockItem('SALT'), null, '5', 'Found');
    app(SubmitStockCount::class)($count, $this->counter);

    $approved = app(ApproveStockCount::class)($count, $this->manager, 'Agreed');

    $loss = InventoryTransaction::where('type', T::Adjustment)->where('quantity', '<', 0)->sole();
    $gain = InventoryTransaction::where('type', T::Adjustment)->where('quantity', '>', 0)->sole();

    expect($approved->status)->toBe(StockCountStatus::Approved)->and($approved->decided_by)->toBe($this->manager->id)
        ->and($loss->value_minor)->toBe(-1000)->and($loss->source_type)->toBe('stock_count')->and($loss->source_id)->toBe($count->id)
        ->and((string) $gain->quantity)->toBe('5.000')
        ->and(app(GetStockLevels::class)->total(stockItem('MAIZE')->id))->toBe('90.000');

    expect(fn () => app(ApproveStockCount::class)($count, $this->manager))->toThrow(DomainException::class, 'submitted');
});

it('leaves lines that match the system alone', function () {
    $maize = stockItem('MAIZE');
    receiveStock($maize, '50', 100);
    $count = app(StartStockCount::class)(store(), now()->startOfDay(), null, $this->counter);
    app(RecordCountLine::class)($count, $maize, null, '50');
    app(SubmitStockCount::class)($count, $this->counter);

    app(ApproveStockCount::class)($count, $this->manager);

    expect(InventoryTransaction::where('type', T::Adjustment)->count())->toBe(0);
});

it('lets the owner approve their own count only when separate approval is switched off', function () {
    $owner = owner();
    $maize = stockItem('MAIZE');
    receiveStock($maize, '10', 100);
    $count = app(StartStockCount::class)(store(), now()->startOfDay(), null, $owner);
    app(RecordCountLine::class)($count, $maize, null, '9', 'Spillage');
    app(SubmitStockCount::class)($count, $owner);

    expect(fn () => app(ApproveStockCount::class)($count, $owner))->toThrow(DomainException::class, 'someone other than');

    app(ResolveSettings::class)->set('inventory.require_separate_approver', false);
    expect(app(ApproveStockCount::class)($count, $owner)->status)->toBe(StockCountStatus::Approved);
});

it('refuses an approval that would take stock below zero', function () {
    $count = draftCount($this->counter);
    app(SubmitStockCount::class)($count, $this->counter);
    issueStock(stockItem('MAIZE'), '95');   // stock used after the count was taken

    expect(fn () => app(ApproveStockCount::class)($count, $this->manager))->toThrow(DomainException::class, 'Not enough stock');
    expect($count->fresh()->status)->toBe(StockCountStatus::Submitted);
});

it('rejects or cancels a count without touching stock', function () {
    $count = draftCount($this->counter);
    app(SubmitStockCount::class)($count, $this->counter);

    expect(fn () => app(RejectStockCount::class)($count, $this->manager, ' '))->toThrow(DomainException::class, 'reason');
    $rejected = app(RejectStockCount::class)($count, $this->manager, 'Recount tomorrow');

    expect($rejected->status)->toBe(StockCountStatus::Rejected)
        ->and(InventoryTransaction::where('type', T::Adjustment)->count())->toBe(0);

    $next = app(StartStockCount::class)(store(), now()->startOfDay());
    expect(app(CancelStockCount::class)($next)->status)->toBe(StockCountStatus::Cancelled);
    expect(fn () => app(CancelStockCount::class)($count))->toThrow(DomainException::class);
});

it('moves stock for an adjustment only once it is approved', function () {
    $maize = stockItem('MAIZE');
    receiveStock($maize, '100', 100);

    $adjustment = app(RequestStockAdjustment::class)($maize, store(), null, '-8', 'Rodent damage', $this->counter);

    expect($adjustment->number)->toStartWith('ADJ-')->and($adjustment->status)->toBe(ApprovalStatus::Pending)
        ->and(app(GetStockLevels::class)->total($maize->id))->toBe('100.000');
    expect(fn () => app(ApproveStockAdjustment::class)($adjustment, $this->counter))->toThrow(DomainException::class, 'not authorised');

    app(ApproveStockAdjustment::class)($adjustment, $this->manager, 'Seen it');

    expect(app(GetStockLevels::class)->total($maize->id))->toBe('92.000')
        ->and($adjustment->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and(InventoryTransaction::where('source_type', 'stock_adjustment')->sole()->value_minor)->toBe(-800);
    expect(fn () => app(ApproveStockAdjustment::class)($adjustment, $this->manager))->toThrow(DomainException::class, 'already been decided');
    expect(fn () => $adjustment->fresh()->update(['quantity' => '5']))->toThrow(LogicException::class);
});

it('rejects an adjustment, and validates the request', function () {
    $maize = stockItem('MAIZE');
    receiveStock($maize, '10', 100);

    expect(fn () => app(RequestStockAdjustment::class)($maize, store(), null, '0', 'x'))->toThrow(DomainException::class, 'non-zero');
    expect(fn () => app(RequestStockAdjustment::class)($maize, store(), null, '1', ' '))->toThrow(DomainException::class, 'reason');

    $adjustment = app(RequestStockAdjustment::class)($maize, store(), null, '3', 'Found stock', $this->counter);
    app(RejectStockAdjustment::class)($adjustment, $this->manager, 'Not credible');

    expect($adjustment->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and(StockAdjustment::count())->toBe(1)
        ->and(app(GetStockLevels::class)->total($maize->id))->toBe('10.000');
});

it('lists items at or below their reorder level with a suggested quantity', function () {
    $maize = stockItem('MAIZE', ['reorder_level' => '50', 'reorder_quantity' => '200']);
    $soya = stockItem('SOYA', ['reorder_level' => '10']);
    stockItem('SALT');   // no reorder level
    receiveStock($maize, '50', 100);
    receiveStock($soya, '25', 100);

    $alerts = app(GetReorderAlerts::class)();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()['item']->code)->toBe('MAIZE')
        ->and($alerts->first()['suggested_quantity'])->toBe('200.000');

    issueStock($soya, '20');
    $alerts = app(GetReorderAlerts::class)();

    expect($alerts->pluck('item.code')->sort()->values()->all())->toBe(['MAIZE', 'SOYA'])
        ->and($alerts->first(fn ($a) => $a['item']->code === 'SOYA')['suggested_quantity'])->toBe('5.000');
});

it('warns about batches that are expired or expire soon while they still hold stock', function () {
    $vaccine = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
    foreach ([['SOON', 20], ['LATER', 200], ['EMPTY', 10]] as [$lot, $days]) {
        receiveStock($vaccine, '10', 100, daysAgo: 1, details: ['batch_number' => $lot, 'expiry_date' => now()->addDays($days)->toDateString()]);
    }
    issueStock($vaccine, '10', details: ['batch' => InventoryBatch::firstWhere('batch_number', 'EMPTY')]);

    $alerts = app(GetExpiryAlerts::class)();

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()['batch']->batch_number)->toBe('SOON')
        ->and($alerts->first()['days_left'])->toBe(20)
        ->and($alerts->first()['expired'])->toBeFalse();
});

describe('feed taken from a store', function () {
    beforeEach(function () {
        $this->meal = stockItem('GROWER-MEAL', ['category' => InventoryCategory::FinishedFeed, 'feed_type_id' => growerFeed()->id]);
        receiveStock($this->meal, '1000', 40000, store('FEED'), 3);   // 400.00 per kg
    });

    it('takes the feed out of stock and costs it from the ledger', function () {
        $record = feed(openBatch(), '100', extra: ['inventory_location_id' => store('FEED')->id]);

        expect($record->cost_minor)->toBe(4000000)->and($record->cost_per_kg_minor)->toBe(40000)
            ->and($record->inventory_group)->not->toBeNull()
            ->and(app(GetStockLevels::class)->total($this->meal->id))->toBe('900.000')
            ->and(InventoryTransaction::where('group_uuid', $record->inventory_group)->sole()->type)->toBe(T::Consumption);
    });

    it('puts the feed back when the record is voided', function () {
        $record = feed(openBatch(), '100', extra: ['inventory_location_id' => store('FEED')->id]);

        app(VoidFeedConsumption::class)($record, 'Entered twice');

        expect(app(GetStockLevels::class)->total($this->meal->id))->toBe('1000.000')
            ->and($record->fresh()->isVoided())->toBeTrue();
    });

    it('refuses feed the store does not hold, and records nothing', function () {
        $batch = openBatch();

        expect(fn () => feed($batch, '1500', extra: ['inventory_location_id' => store('FEED')->id]))->toThrow(DomainException::class, 'Not enough stock');
        expect($batch->feedRecords()->count())->toBe(0)
            ->and(app(GetStockLevels::class)->total($this->meal->id))->toBe('1000.000');
    });

    it('needs the feed type to be linked to a stock item', function () {
        $other = FeedType::firstWhere('code', 'STARTER');

        expect(fn () => app(RecordFeedConsumption::class)(openBatch(), $other, now()->startOfDay(), '10', ['inventory_location_id' => store('FEED')->id]))
            ->toThrow(DomainException::class, 'not linked to a stock item');
    });

    it('still records feed without touching stock when no store is named', function () {
        $record = feed(openBatch(), '50', perKg: 25000);

        expect($record->cost_minor)->toBe(1250000)->and($record->inventory_group)->toBeNull()
            ->and(app(GetStockLevels::class)->total($this->meal->id))->toBe('1000.000');
    });
});

it('grants stock rights by role', function () {
    expect($this->counter->can('inventory.create'))->toBeTrue()
        ->and($this->counter->can('inventory.approve'))->toBeFalse()
        ->and($this->manager->can('inventory.approve'))->toBeTrue()
        ->and(farmWorker()->can('inventory.edit'))->toBeFalse()
        ->and($this->manager->can('delete', receiveStock(stockItem(), '1', 1)))->toBeFalse();
});
