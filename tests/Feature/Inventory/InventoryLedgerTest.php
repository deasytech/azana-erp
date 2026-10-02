<?php

use App\Domain\Farm\Actions\ResolveSettings;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Actions\IssueStock;
use App\Domain\Inventory\Actions\PostInventoryTransaction;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Actions\ReverseInventoryTransaction;
use App\Domain\Inventory\Actions\TransferStock;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryLayer;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\InventoryTransactionType as T;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

/** The cost layers must always agree with the ledger. */
function expectLedgerToMatchLayers(): void
{
    expect(bcadd((string) InventoryLayer::sum('remaining_quantity'), '0', 3))->toBe(bcadd((string) InventoryTransaction::sum('quantity'), '0', 3))
        ->and((int) InventoryLayer::sum('remaining_value_minor'))->toBe((int) InventoryTransaction::sum('value_minor'));
}

it('records every stock change as a ledger line with its value', function () {
    $item = stockItem();
    $line = receiveStock($item, '100', 35050);

    expect($line->type)->toBe(T::Receipt)
        ->and((string) $line->quantity)->toBe('100.000')
        ->and($line->value_minor)->toBe(3505000);

    issueStock($item, '40');

    expect(InventoryTransaction::count())->toBe(2)
        ->and(app(GetStockLevels::class)->total($item->id))->toBe('60.000');
    expectLedgerToMatchLayers();
});

it('values issues oldest cost first under FIFO', function () {
    $item = stockItem();
    receiveStock($item, '100', 100, daysAgo: 5);
    receiveStock($item, '100', 200, daysAgo: 2);

    $issued = issueStock($item, '150')->sole();

    expect($issued->value_minor)->toBe(-(10000 + 10000));

    $left = app(GetStockLevels::class)()->sole();
    expect($left->on_hand)->toBe('50.000')->and($left->value_minor)->toBe(10000);
    expectLedgerToMatchLayers();
});

it('values issues at the average cost when weighted average is configured', function () {
    app(ResolveSettings::class)->set('inventory.valuation_method', 'weighted_average');
    $item = stockItem();
    receiveStock($item, '100', 100, daysAgo: 5);
    receiveStock($item, '100', 200, daysAgo: 2);

    $issued = issueStock($item, '150')->sole();

    expect($issued->value_minor)->toBe(-22500);

    $left = app(GetStockLevels::class)()->sole();
    expect($left->on_hand)->toBe('50.000')->and($left->value_minor)->toBe(7500);
    expectLedgerToMatchLayers();
});

it('rejects an unknown valuation method', function () {
    app(ResolveSettings::class)->set('inventory.valuation_method', 'lifo');
})->throws(DomainException::class);

it('never loses a minor unit to rounding, under either method', function (string $method) {
    app(ResolveSettings::class)->set('inventory.valuation_method', $method);
    $item = stockItem();
    app(ReceiveStock::class)(T::Receipt, $item, store(), '3', now()->startOfDay(), ['value_minor' => 100]);

    $issued = collect(range(1, 3))->sum(fn () => -issueStock($item, '1')->sole()->value_minor);

    expect($issued)->toBe(100)
        ->and(app(GetStockLevels::class)())->toBeEmpty();
    expectLedgerToMatchLayers();
})->with(['fifo', 'weighted_average']);

it('refuses to take out more than is held and leaves the ledger untouched', function () {
    $item = stockItem();
    receiveStock($item, '10', 100);

    expect(fn () => issueStock($item, '10.001'))->toThrow(DomainException::class, 'Not enough stock');
    expect(InventoryTransaction::count())->toBe(1);
    expectLedgerToMatchLayers();
});

it('checks the quantity, its direction and that a cost is given', function () {
    $item = stockItem();
    $post = app(PostInventoryTransaction::class);

    expect(fn () => $post(T::Consumption, $item, store(), '5', now(), []))->toThrow(DomainException::class, 'must remove stock');
    expect(fn () => $post(T::Receipt, $item, store(), '-5', now(), ['unit_cost_minor' => 1]))->toThrow(DomainException::class, 'must add stock');
    expect(fn () => $post(T::Receipt, $item, store(), '0', now(), ['unit_cost_minor' => 1]))->toThrow(DomainException::class, 'non-zero');
    expect(fn () => $post(T::Receipt, $item, store(), '1.2345', now(), ['unit_cost_minor' => 1]))->toThrow(DomainException::class, '3 decimals');
    expect(fn () => $post(T::Receipt, $item, store(), '5', now(), []))->toThrow(DomainException::class, 'needs a cost');
    expect(fn () => $post(T::Receipt, $item, store(), '5', now()->addDay(), ['unit_cost_minor' => 1]))->toThrow(DomainException::class, 'future');
    expect(InventoryTransaction::count())->toBe(0);
});

it('requires a batch for batch-tracked items and creates it on receipt', function () {
    $item = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
    $supplier = Supplier::create(['code' => 'SUP1', 'name' => 'Agro Ltd']);

    expect(fn () => receiveStock($item, '10', 500))->toThrow(DomainException::class, 'batch number');
    expect(fn () => receiveStock($item, '10', 500, details: ['batch_number' => 'LOT1']))->toThrow(DomainException::class, 'expiry date');

    $line = receiveStock($item, '10', 500, details: ['batch_number' => ' LOT1 ', 'expiry_date' => now()->addMonths(6)->toDateString(), 'supplier_id' => $supplier->id]);

    $batch = InventoryBatch::sole();
    expect($line->inventory_batch_id)->toBe($batch->id)
        ->and($batch->batch_number)->toBe('LOT1')
        ->and($batch->supplier_id)->toBe($supplier->id)
        ->and($batch->expiry_date->isFuture())->toBeTrue();

    // A second receipt of the same lot reuses the batch, but cannot contradict its expiry.
    receiveStock($item, '5', 500, details: ['batch_number' => 'LOT1', 'expiry_date' => now()->addMonths(6)->toDateString()]);
    expect(InventoryBatch::count())->toBe(1);
    expect(fn () => receiveStock($item, '5', 500, details: ['batch_number' => 'LOT1', 'expiry_date' => now()->addYear()->toDateString()]))
        ->toThrow(DomainException::class, 'different expiry');
});

it('treats an item that expires as tracked by batch', function () {
    expect(stockItem('X', ['tracks_expiry' => true])->tracks_batches)->toBeTrue();
});

it('will not receive expired stock or use an expired batch, but allows writing it off', function () {
    $item = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);

    expect(fn () => receiveStock($item, '10', 500, details: ['batch_number' => 'OLD', 'expiry_date' => now()->subDay()->toDateString()]))
        ->toThrow(DomainException::class, 'expired');

    receiveStock($item, '10', 500, daysAgo: 30, details: ['batch_number' => 'OLD', 'expiry_date' => now()->subDays(10)->toDateString()]);
    $batch = InventoryBatch::sole();

    expect(fn () => issueStock($item, '1', details: ['batch' => $batch]))->toThrow(DomainException::class, 'expired');
    expect(fn () => issueStock($item, '1'))->toThrow(DomainException::class, 'Not enough usable stock');

    $written = issueStock($item, '10', type: T::Wastage, details: ['batch' => $batch, 'reason' => 'Expired']);
    expect($written->sole()->value_minor)->toBe(-5000);
    expectLedgerToMatchLayers();
});

it('uses the batch closest to expiry first and skips expired ones', function () {
    $item = stockItem('VAC', ['tracks_batches' => true, 'tracks_expiry' => true]);
    receiveStock($item, '10', 100, daysAgo: 40, details: ['batch_number' => 'LATE', 'expiry_date' => now()->addMonths(6)->toDateString()]);
    receiveStock($item, '10', 100, daysAgo: 30, details: ['batch_number' => 'SOON', 'expiry_date' => now()->addMonth()->toDateString()]);
    receiveStock($item, '10', 100, daysAgo: 60, details: ['batch_number' => 'GONE', 'expiry_date' => now()->subDay()->toDateString()]);

    $lines = issueStock($item, '15');

    expect($lines->pluck('inventory_batch_id')->map(fn ($id) => InventoryBatch::find($id)->batch_number)->all())->toBe(['SOON', 'LATE'])
        ->and($lines->map(fn ($l) => (string) $l->quantity)->all())->toBe(['-10.000', '-5.000'])
        ->and($lines->pluck('group_uuid')->unique())->toHaveCount(1);
    expectLedgerToMatchLayers();
});

it('replays an issue with the same idempotency key without taking stock twice', function () {
    $item = stockItem('VAC', ['tracks_batches' => true]);
    receiveStock($item, '10', 100, details: ['batch_number' => 'A']);
    receiveStock($item, '10', 100, details: ['batch_number' => 'B']);

    $first = issueStock($item, '15', details: ['idempotency_key' => 'issue-1']);
    $again = issueStock($item, '15', details: ['idempotency_key' => 'issue-1']);

    expect($first)->toHaveCount(2)->and($again->pluck('id')->all())->toBe($first->pluck('id')->all())
        ->and(app(GetStockLevels::class)->total($item->id))->toBe('5.000');
});

it('rejects an idempotency key reused for different stock', function () {
    $item = stockItem();
    receiveStock($item, '10', 100, details: ['idempotency_key' => 'k1']);

    expect(fn () => receiveStock($item, '20', 100, details: ['idempotency_key' => 'k1']))->toThrow(DomainException::class, 'idempotency');
    expect(InventoryTransaction::count())->toBe(1);
});

it('transfers stock between stores at the cost it left with', function () {
    $item = stockItem();
    $main = store('MAIN');
    $silo = store('SILO');
    receiveStock($item, '100', 100, $main, 5);
    receiveStock($item, '100', 200, $main, 2);

    $legs = app(TransferStock::class)($item, $main, $silo, '150', now()->startOfDay(), ['reason' => 'Move to silo']);

    expect($legs)->toHaveCount(2)
        ->and($legs->pluck('group_uuid')->unique())->toHaveCount(1)
        ->and($legs->where('type', T::TransferIn)->sum('value_minor'))->toBe(20000)
        ->and(app(GetStockLevels::class)(locationId: $silo->id)->sum('value_minor'))->toBe(20000)
        ->and(app(GetStockLevels::class)->total($item->id))->toBe('200.000');
    expectLedgerToMatchLayers();

    expect(fn () => app(TransferStock::class)($item, $silo, $silo, '1', now()))->toThrow(DomainException::class, 'two different stores');
    expect(fn () => app(TransferStock::class)($item, $silo, $main, '1000', now()))->toThrow(DomainException::class, 'Not enough stock');
});

it('reverses a receipt only while all of it is still there', function () {
    $item = stockItem();
    $receipt = receiveStock($item, '10', 100);

    $reversal = app(ReverseInventoryTransaction::class)($receipt, 'Wrong item received');

    expect((string) $reversal->quantity)->toBe('-10.000')->and($reversal->reverses_id)->toBe($receipt->id)
        ->and(app(GetStockLevels::class)())->toBeEmpty();
    expectLedgerToMatchLayers();

    expect(fn () => app(ReverseInventoryTransaction::class)($receipt, 'Again'))->toThrow(DomainException::class, 'already been reversed');
    expect(fn () => app(ReverseInventoryTransaction::class)($reversal, 'Undo'))->toThrow(DomainException::class, 'cannot itself be reversed');

    $used = receiveStock($item, '10', 100);
    issueStock($item, '4');
    expect(fn () => app(ReverseInventoryTransaction::class)($used, 'Oops'))->toThrow(DomainException::class, 'already been used');
});

it('puts reversed issues back at the value they left with', function () {
    $item = stockItem();
    receiveStock($item, '100', 100, daysAgo: 5);
    receiveStock($item, '100', 200, daysAgo: 2);
    $issued = issueStock($item, '150')->sole();

    app(ReverseInventoryTransaction::class)($issued, 'Entered in error');

    expect(app(GetStockLevels::class)->total($item->id))->toBe('200.000')
        ->and(app(GetStockLevels::class)->totalValue())->toBe(30000);
    expectLedgerToMatchLayers();

    expect(fn () => app(ReverseInventoryTransaction::class)($issued, ''))->toThrow(DomainException::class, 'reason');
});

it('never lets ledger lines be edited or deleted', function () {
    $line = receiveStock(stockItem(), '10', 100);

    expect(fn () => $line->update(['quantity' => 99]))->toThrow(LogicException::class);
    expect(fn () => $line->delete())->toThrow(LogicException::class);
});

it('keeps stock in separate stores and for items without batches only', function () {
    $item = stockItem();
    receiveStock($item, '10', 100, store('MAIN'));

    expect(fn () => issueStock($item, '1', store('OTHER')))->toThrow(DomainException::class, 'Not enough stock');
    expect(fn () => receiveStock($item, '1', 100, details: ['batch_number' => 'X']))->not->toThrow(DomainException::class)
        ->and(InventoryBatch::count())->toBe(0);
});

it('uses the batch of another item only if it matches', function () {
    $a = stockItem('A', ['tracks_batches' => true]);
    $b = stockItem('B', ['tracks_batches' => true]);
    receiveStock($a, '5', 100, details: ['batch_number' => 'L1']);
    $batch = InventoryBatch::sole();

    expect(fn () => issueStock($b, '1', details: ['batch' => $batch]))->toThrow(DomainException::class, 'different item');
});

it('valuation setting defaults to FIFO', function () {
    expect(app(ResolveSettings::class)->get('inventory.valuation_method'))->toBe('fifo');
});

it('posts one stock line per call through the single ledger entry point', function () {
    $post = app(PostInventoryTransaction::class);
    $line = $post(T::Opening, stockItem(), store(), '25', now()->subDay(), ['unit_cost_minor' => 400, 'reason' => 'Opening balance']);

    expect($line->value_minor)->toBe(10000)->and($line->reason)->toBe('Opening balance')
        ->and(app(IssueStock::class))->not->toBeNull();
});
