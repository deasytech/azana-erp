<?php

use App\Domain\Farm\Models\UnitOfMeasure;
use App\Domain\Feed\Actions\CalculateFeedRequirements;
use App\Domain\Feed\Actions\CompleteFeedProduction;
use App\Domain\Feed\Actions\ConfirmFeedConsumption;
use App\Domain\Feed\Actions\CreateFeedProductionOrder;
use App\Domain\Feed\Actions\GetFormulaCost;
use App\Domain\Feed\Actions\ManageFeedFormulaVersions;
use App\Domain\Feed\Actions\ManageFeedProductionOrder;
use App\Domain\Feed\Actions\SaveFeedFormula;
use App\Domain\Feed\Models\FeedFormula;
use App\Domain\Feed\Models\FeedProductionBatch;
use App\Domain\Inventory\Actions\GetStockLevels;
use App\Domain\Inventory\Models\InventoryTransaction;
use App\Domain\System\Exceptions\DomainException;
use App\Enums\FeedProductionStatus as Status;
use App\Enums\FormulaStatus;
use App\Enums\InventoryTransactionType as T;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
});

function formulaData(array $over = []): array
{
    return array_merge([
        'code' => 'sow-gest', 'name' => 'Sow gestation', 'feed_type_id' => growerFeed()->id,
        'items' => [['inventory_item_id' => stockItem('MAIZE')->id, 'inclusion_percent' => '70'], ['inventory_item_id' => stockItem('SOYA')->id, 'inclusion_percent' => '30']],
    ], $over);
}

describe('formulas', function () {
    it('saves a draft with its nutrition and ingredients, even before they add up to 100%', function () {
        $formula = app(SaveFeedFormula::class)(formulaData(['items' => [['inventory_item_id' => stockItem('MAIZE')->id, 'inclusion_percent' => '55.5']], 'crude_protein_percent' => '14.25', 'energy_kcal_per_kg' => '3100']));

        expect($formula->code)->toBe('SOW-GEST')->and($formula->version)->toBe(1)->and($formula->status)->toBe(FormulaStatus::Draft)
            ->and($formula->crude_protein_percent)->toBe('14.25')->and($formula->energy_kcal_per_kg)->toBe('3100.00')
            ->and((string) $formula->items->sole()->inclusion_percent)->toBe('55.5000');
    });

    it('checks what it is given', function () {
        $save = app(SaveFeedFormula::class);
        $maize = stockItem('MAIZE')->id;

        expect(fn () => $save(formulaData(['name' => ' '])))->toThrow(DomainException::class, 'name');
        expect(fn () => $save(formulaData(['feed_type_id' => 9999])))->toThrow(DomainException::class, 'feed type');
        expect(fn () => $save(formulaData(['items' => []])))->toThrow(DomainException::class, 'at least one ingredient');
        expect(fn () => $save(formulaData(['process_loss_percent' => '60'])))->toThrow(DomainException::class, 'process loss');
        expect(fn () => $save(formulaData(['crude_protein_percent' => '101'])))->toThrow(DomainException::class, 'Crude protein');
        expect(fn () => $save(formulaData(['items' => [['inventory_item_id' => $maize, 'inclusion_percent' => '0']]])))->toThrow(DomainException::class, 'above 0');
        expect(fn () => $save(formulaData(['items' => [['inventory_item_id' => $maize, 'inclusion_percent' => '101']]])))->toThrow(DomainException::class, 'at most 100');
        expect(fn () => $save(formulaData(['items' => [['inventory_item_id' => $maize, 'inclusion_percent' => '1.00001']]])))->toThrow(DomainException::class, '4 decimals');
        expect(fn () => $save(formulaData(['items' => [['inventory_item_id' => $maize, 'inclusion_percent' => '50'], ['inventory_item_id' => $maize, 'inclusion_percent' => '50']]])))->toThrow(DomainException::class, 'only once');
        expect(FeedFormula::count())->toBe(0);

        $save(formulaData());
        expect(fn () => $save(formulaData()))->toThrow(DomainException::class, 'already used');
    });

    it('can be changed only while it is a draft', function () {
        stockItem('GROWER-MEAL', ['feed_type_id' => growerFeed()->id]);
        $formula = app(SaveFeedFormula::class)(formulaData());
        $changed = app(SaveFeedFormula::class)(formulaData(['name' => 'Renamed', 'items' => [['inventory_item_id' => stockItem('MAIZE')->id, 'inclusion_percent' => '100']]]), $formula);

        expect($changed->name)->toBe('Renamed')->and($changed->items)->toHaveCount(1);

        app(ManageFeedFormulaVersions::class)->activate($changed);
        expect(fn () => app(SaveFeedFormula::class)(formulaData(), $changed->fresh()))->toThrow(DomainException::class, 'draft');
    });

    it('is activated only when complete, and replaces the previous active version', function () {
        $versions = app(ManageFeedFormulaVersions::class);
        stockItem('GROWER-MEAL', ['feed_type_id' => growerFeed()->id]);
        $partial = app(SaveFeedFormula::class)(formulaData(['items' => [['inventory_item_id' => stockItem('MAIZE')->id, 'inclusion_percent' => '55.5']]]));

        expect(fn () => $versions->activate($partial))->toThrow(DomainException::class, 'add up to 55.5000%');

        $v1 = $versions->activate(app(SaveFeedFormula::class)(formulaData(['code' => 'ok-1'])));
        expect($v1->status)->toBe(FormulaStatus::Active)->and($v1->activated_at)->not->toBeNull();

        $v2 = $versions->newVersion($v1);
        expect($v2->version)->toBe(2)->and($v2->status)->toBe(FormulaStatus::Draft)->and($v2->items)->toHaveCount(2);
        expect(fn () => $versions->newVersion($v1))->toThrow(DomainException::class, 'already has a draft');

        $versions->activate($v2);
        expect($v1->fresh()->status)->toBe(FormulaStatus::Retired)->and($v2->fresh()->status)->toBe(FormulaStatus::Active);
        expect(fn () => $versions->activate($v1))->toThrow(DomainException::class, 'draft');
        expect($versions->retire($v2)->status)->toBe(FormulaStatus::Retired);
        expect(fn () => $versions->retire($v2))->toThrow(DomainException::class, 'active');
    });

    it('numbers a new version after the latest one, whichever version it is copied from', function () {
        $versions = app(ManageFeedFormulaVersions::class);
        $v1 = millFixture()['formula'];
        $v2 = $versions->activate($versions->newVersion($v1));

        $v3 = $versions->newVersion($v1->fresh());   // copied from the old, retired version

        expect($v2->version)->toBe(2)->and($v3->version)->toBe(3)->and($v3->items)->toHaveCount(3)->and($v3->code)->toBe($v1->code);
    });

    it('cannot be activated without a stock item for its finished feed', function () {
        $formula = app(SaveFeedFormula::class)(formulaData());

        expect(fn () => app(ManageFeedFormulaVersions::class)->activate($formula))->toThrow(DomainException::class, 'Link a stock item');
    });

    it('calculates the materials for an amount of feed, allowing for process loss', function () {
        $mill = millFixture();

        $rows = app(CalculateFeedRequirements::class)($mill['formula'], '1000')->mapWithKeys(fn ($r) => [$r['item']->code => $r['quantity']]);

        expect($rows->all())->toBe(['MAIZE' => '612.245', 'SOYA' => '306.122', 'PREMIX' => '102.041']);
        expect(fn () => app(CalculateFeedRequirements::class)($mill['formula'], '0'))->toThrow(DomainException::class, 'positive');
    });

    it('states quantities in each ingredient\'s own unit', function () {
        $mill = millFixture();
        $mill['maize']->update(['unit_id' => UnitOfMeasure::firstWhere('code', 'TON')->id]);

        $maize = app(CalculateFeedRequirements::class)($mill['formula'], '1000')->first(fn ($r) => $r['item']->code === 'MAIZE');

        expect($maize['quantity'])->toBe('0.612');
    });

    it('works out cost per kg, bag and tonne from what the ingredients cost', function () {
        $cost = app(GetFormulaCost::class)(millFixture()['formula']);

        expect($cost['per_kg_minor'])->toBe(69388)->and($cost['per_bag_minor'])->toBe(1734694)->and($cost['per_tonne_minor'])->toBe(69387755)
            ->and($cost['bag_kg'])->toBe('25')->and($cost['unpriced'])->toBe([]);
    });

    it('says which ingredients have no cost yet', function () {
        $mill = millFixture();
        $extra = stockItem('MINERALS');
        app(SaveFeedFormula::class)(formulaData(['code' => 'new', 'items' => [['inventory_item_id' => $extra->id, 'inclusion_percent' => '100']]]));

        expect(app(GetFormulaCost::class)(FeedFormula::firstWhere('code', 'NEW'))['unpriced'])->toBe(['Item MINERALS']);
    });
});

describe('production orders', function () {
    it('plans the materials from the formula and keeps them', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);

        expect($order->number)->toStartWith('FM-')->and($order->status)->toBe(Status::Planned)
            ->and($order->lines->pluck('planned_quantity', 'inventory_item_id')->map(fn ($q) => (string) $q)->all())
            ->toBe([$mill['maize']->id => '612.245', $mill['soya']->id => '306.122', $mill['premix']->id => '102.041']);

        // A new version of the formula does not change the order already made.
        $v2 = app(ManageFeedFormulaVersions::class)->newVersion($mill['formula']);
        app(SaveFeedFormula::class)(formulaData(['name' => 'Changed', 'items' => [['inventory_item_id' => $mill['maize']->id, 'inclusion_percent' => '100']]]) + ['code' => $v2->code], $v2);
        app(ManageFeedFormulaVersions::class)->activate($v2->fresh());

        expect($order->fresh()->lines)->toHaveCount(3);
    });

    it('needs an active formula and active stores', function () {
        $mill = millFixture();
        $draft = app(SaveFeedFormula::class)(formulaData());
        $create = app(CreateFeedProductionOrder::class);

        expect(fn () => $create($draft, '100', now(), $mill['raw'], $mill['out']))->toThrow(DomainException::class, 'not an active formula');
        expect(fn () => $create($mill['formula'], '0.0001', now(), $mill['raw'], $mill['out']))->toThrow(DomainException::class);

        $closed = store('CLOSED');
        $closed->update(['is_active' => false]);
        expect(fn () => $create($mill['formula'], '100', now(), $closed, $mill['out']))->toThrow(DomainException::class, 'active stores');
    });

    it('shows what the store is short of', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill, '5000');   // needs about 3061 kg maize, only 1000 on hand

        $rows = app(ManageFeedProductionOrder::class)->availability($order)->keyBy(fn ($r) => $r['line']->item->code);

        expect($rows['MAIZE']['on_hand'])->toBe('1000.000')->and($rows['MAIZE']['short'])->toBe('2061.224')
            ->and($rows['SOYA']['short'])->toBe('1030.612')->and($rows['PREMIX']['short'])->toBe('310.204');
        expect(app(ManageFeedProductionOrder::class)->availability(plannedFeedOrder($mill, '100'))->every(fn ($r) => $r['short'] === '0.000'))->toBeTrue();
    });

    it('lets production staff confirm what was actually used', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);
        $confirm = app(ConfirmFeedConsumption::class);

        expect(fn () => $confirm($order, []))->toThrow(DomainException::class, 'at least one');
        expect(fn () => $confirm($order, [999 => '1']))->toThrow(DomainException::class, 'not part of this order');
        expect(fn () => $confirm($order, [$mill['maize']->id => '-1']))->toThrow(DomainException::class, 'zero or more');

        $confirm($order, [$mill['maize']->id => '620']);
        expect($order->fresh()->lines->firstWhere('inventory_item_id', $mill['maize']->id)->actual_quantity)->toBe('620.000');

        $confirm->asPlanned($order);
        $lines = $order->fresh()->lines->keyBy('inventory_item_id');
        expect((string) $lines[$mill['maize']->id]->actual_quantity)->toBe('620.000')   // an entered figure is kept
            ->and((string) $lines[$mill['soya']->id]->actual_quantity)->toBe('306.122');
    });

    it('can be cancelled only while planned', function () {
        $order = plannedFeedOrder(millFixture());

        expect(fn () => app(ManageFeedProductionOrder::class)->cancel($order, ' '))->toThrow(DomainException::class, 'reason');
        expect(app(ManageFeedProductionOrder::class)->cancel($order, 'Not needed')->status)->toBe(Status::Cancelled);
        expect(fn () => app(ManageFeedProductionOrder::class)->cancel($order, 'Again'))->toThrow(DomainException::class, 'planned');
        expect(fn () => app(ConfirmFeedConsumption::class)->asPlanned($order->fresh()))->toThrow(DomainException::class, 'planned order');
    });
});

describe('completing production', function () {
    it('consumes the materials, makes the finished feed and works out the cost', function () {
        $mill = millFixture();
        $batch = completedFeedRun($mill);

        expect($batch->material_cost_minor)->toBe(69387755)->and($batch->other_cost_minor)->toBe(1000000)
            ->and($batch->total_cost_minor)->toBe(70387755)->and($batch->cost_per_kg_minor)->toBe(71099)
            ->and((string) $batch->output_kg)->toBe('990.000')
            ->and($batch->order->status)->toBe(Status::Completed);

        $levels = app(GetStockLevels::class);
        expect($levels->total($mill['maize']->id))->toBe('387.755')      // 1000 - 612.245
            ->and($levels->total($mill['soya']->id))->toBe('193.878')
            ->and($levels->total($mill['premix']->id))->toBe('97.959');

        $finished = $levels(itemId: $mill['finished']->id)->sole();
        expect($finished->on_hand)->toBe('990.000')->and($finished->value_minor)->toBe(70387755)
            ->and($finished->location->code)->toBe('FINISHED')
            ->and($finished->batch->batch_number)->toBe($batch->order->number)
            ->and($finished->batch->expiry_date->toDateString())->toBe(now()->addDays(90)->toDateString());
    });

    it('records every stock movement of the run in the ledger as one group', function () {
        $mill = millFixture();
        $batch = completedFeedRun($mill);

        $lines = InventoryTransaction::where('group_uuid', $batch->group_uuid)->get();

        expect($lines)->toHaveCount(4)
            ->and($lines->where('type', T::Consumption)->count())->toBe(3)
            ->and($lines->where('type', T::Production)->sole()->value_minor)->toBe(70387755)
            ->and($lines->pluck('source_type')->unique()->all())->toBe(['feed_production'])
            ->and($lines->pluck('source_id')->unique()->all())->toBe([$batch->feed_production_order_id])
            ->and($lines->sum('value_minor'))->toBe(1000000);   // materials out, feed in: only the other costs are added to stock value

        // Raw material batch -> supplier stays traceable from the run.
        $premix = $lines->first(fn ($l) => $l->inventory_item_id === $mill['premix']->id);
        expect($premix->batch->batch_number)->toBe('PX-1')->and($premix->batch->supplier_id)->not->toBeNull();
    });

    it('uses the quantities production staff confirmed, not the plan', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);
        app(ConfirmFeedConsumption::class)($order, [$mill['maize']->id => '620', $mill['soya']->id => '300', $mill['premix']->id => '0']);

        $batch = app(CompleteFeedProduction::class)($order, '1000', now()->startOfDay());

        expect($batch->material_cost_minor)->toBe(620 * 35000 + 300 * 90000)
            ->and(app(GetStockLevels::class)->total($mill['premix']->id))->toBe('200.000');   // none used
    });

    it('does nothing at all if any material is short', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);
        app(ConfirmFeedConsumption::class)($order, [$mill['maize']->id => '100', $mill['soya']->id => '100', $mill['premix']->id => '500']);   // only 200 premix on hand
        $ledger = InventoryTransaction::count();

        expect(fn () => app(CompleteFeedProduction::class)($order, '1000', now()->startOfDay()))->toThrow(DomainException::class, 'Not enough');

        expect(InventoryTransaction::count())->toBe($ledger)->and(FeedProductionBatch::count())->toBe(0)
            ->and($order->fresh()->status)->toBe(Status::Planned)
            ->and(app(GetStockLevels::class)->total($mill['maize']->id))->toBe('1000.000');
    });

    it('guards completion', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);
        $complete = app(CompleteFeedProduction::class);

        expect(fn () => $complete($order, '990', now()))->toThrow(DomainException::class, 'Confirm the quantity');
        app(ConfirmFeedConsumption::class)->asPlanned($order);
        expect(fn () => $complete($order, '0', now()))->toThrow(DomainException::class, 'positive');
        expect(fn () => $complete($order, '990', now(), -1))->toThrow(DomainException::class, 'negative');
        expect(fn () => $complete($order, '990', now()->addDay()))->toThrow(DomainException::class, 'future');

        $complete($order, '990', now()->startOfDay());
        expect(fn () => $complete($order, '990', now()))->toThrow(DomainException::class, 'only a planned order');
    });

    it('gives the finished batch a number of your choice', function () {
        $mill = millFixture();
        $order = plannedFeedOrder($mill);
        app(ConfirmFeedConsumption::class)->asPlanned($order);

        $batch = app(CompleteFeedProduction::class)($order, '990', now()->startOfDay(), 0, 'LOT-OCT-01');

        expect($batch->inventoryBatch->batch_number)->toBe('LOT-OCT-01');
    });

    it('is the stock the feed records of Phase 07 are taken from, at the real production cost', function () {
        $mill = millFixture();
        $production = completedFeedRun($mill);

        $record = feed(openBatch(), '100', extra: ['inventory_location_id' => $mill['out']->id]);

        expect($record->cost_minor)->toBe(7109874)->and($record->cost_per_kg_minor)->toBe(71099)
            ->and(app(GetStockLevels::class)->total($mill['finished']->id))->toBe('890.000');

        // Animal -> feed record -> finished batch -> production run -> raw material batch -> supplier
        $consumed = InventoryTransaction::where('group_uuid', $record->inventory_group)->sole();
        expect($consumed->inventory_batch_id)->toBe($production->inventory_batch_id)
            ->and($production->order->lines)->toHaveCount(3);
    });
});

describe('reversing a run', function () {
    it('puts the materials back at their cost and takes the finished feed out', function () {
        $mill = millFixture();
        $batch = completedFeedRun($mill);

        app(ManageFeedProductionOrder::class)->reverse($batch->order, 'Wrong formula used');

        $levels = app(GetStockLevels::class);
        expect($levels->total($mill['maize']->id))->toBe('1000.000')
            ->and($levels->total($mill['finished']->id))->toBe('0.000')
            ->and($batch->fresh()->isReversed())->toBeTrue()
            ->and($batch->order->fresh()->status)->toBe(Status::Reversed)
            ->and($levels->totalValue())->toBe(35000 * 1000 + 90000 * 500 + 200000 * 200);

        expect(fn () => app(ManageFeedProductionOrder::class)->reverse($batch->order, 'Again'))->toThrow(DomainException::class, 'completed');
    });

    it('is refused once any of the finished feed has been used', function () {
        $mill = millFixture();
        $batch = completedFeedRun($mill);
        feed(openBatch(), '10', extra: ['inventory_location_id' => $mill['out']->id]);

        expect(fn () => app(ManageFeedProductionOrder::class)->reverse($batch->order, 'Oops'))->toThrow(DomainException::class, 'already been used');
        expect($batch->order->fresh()->status)->toBe(Status::Completed)->and(app(GetStockLevels::class)->total($mill['maize']->id))->toBe('387.755');
        expect(fn () => app(ManageFeedProductionOrder::class)->reverse($batch->order, ' '))->toThrow(DomainException::class, 'reason');
    });

    it('keeps the finished batch record immutable apart from the reversal', function () {
        $batch = completedFeedRun(millFixture());

        expect(fn () => $batch->update(['output_kg' => 1]))->toThrow(LogicException::class);
        expect(fn () => $batch->delete())->toThrow(LogicException::class);
    });
});

it('grants feed mill rights by role', function () {
    $nutritionist = userWithRole('Nutritionist');
    $mill = userWithRole('Feed Mill Manager');
    $draft = app(SaveFeedFormula::class)(formulaData());

    expect($nutritionist->can('feed-mill.edit'))->toBeTrue()->and($nutritionist->can('feed-mill.approve'))->toBeFalse()
        ->and($mill->can('feed-mill.approve'))->toBeTrue()
        ->and(userWithRole('Store Officer')->can('feed-mill.create'))->toBeFalse()
        ->and(farmWorker()->can('feed-mill.view'))->toBeFalse()
        ->and(owner()->can('delete', $draft))->toBeTrue()
        ->and(owner()->can('delete', millFixture()['formula']))->toBeFalse();
});
