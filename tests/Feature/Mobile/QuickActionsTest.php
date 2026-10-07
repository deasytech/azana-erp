<?php

use App\Domain\Breeding\Models\BreedingService;
use App\Domain\Breeding\Models\Farrowing;
use App\Domain\Inventory\Actions\RejectStockCount;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Litter\Models\Litter;
use App\Domain\Mobile\Actions\ProcessMutation;
use App\Domain\Mobile\Models\SyncMutation;
use App\Enums\InventoryCategory;
use App\Enums\LookupCategory;
use App\Enums\TaskStatus;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

const FIELD_DEVICE = 'device-f1';

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    $this->worker = mobileWorker(FARM_MANAGER);                  // may do all of it
    $this->headers = mobileHeaders($this->worker);
});

/** Sends one quick action and returns its outcome. */
function quick(string $type, array $payload, ?string $clientId = null, ?string $at = null): array
{
    return push([mutation($type, $payload, array_filter(['client_id' => $clientId, 'occurred_at' => $at]))], test()->headers)->assertOk()->json(FIRST_RESULT);
}

it('adds the piglets of a birth to their litter', function () {
    $sow = register();
    serve($sow);
    $litter = farrow($sow, ['born_alive' => 3, 'total_born' => 3, 'stillborn' => 0, 'mummified' => 0]);
    $pen = newPen('FARROW-1');

    $result = quick('add_birth', ['litter' => $litter->litter_number, 'pen_id' => $pen->id, 'piglets' => [['sex' => 'male', 'birth_weight_kg' => 1.4], ['sex' => 'female']]]);

    expect($result)->toMatchArray(['status' => 'accepted', 'server_type' => 'litter', 'server_id' => $litter->id])->and($litter->piglets()->count())->toBe(2)
        ->and($litter->piglets()->first()->animal->weights()->first()->weight_kg)->toBe('1.40');

    // Registering more than were born alive is the domain's rule, and comes back as a rejection.
    $tooMany = quick('add_birth', ['litter' => $litter->litter_number, 'piglets' => [['sex' => 'male'], ['sex' => 'male']]]);

    expect($tooMany['status'])->toBe('rejected')->and($tooMany['error']['code'])->toBe('litter_capacity')->and($litter->piglets()->count())->toBe(2);
});

it('records the weight of a pig, or the average weight of a batch', function () {
    $pig = register();
    $batch = openBatch(['started_on' => now()->subDays(10)->startOfDay()]);

    $a = quick('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 45.5]);
    $b = quick('record_weight', ['batch' => $batch->code, 'average_weight_kg' => 31.2, 'sample_size' => 20]);

    expect($a['status'])->toBe('accepted')->and($pig->weights()->first()->weight_kg)->toBe('45.50')->and($b)->toMatchArray(['status' => 'accepted', 'server_type' => 'batch_weigh_in'])
        ->and($batch->weighIns()->first()->average_weight_kg)->toBe('31.20');

    $both = quick('record_weight', ['animal' => $pig->animal_number, 'batch' => $batch->code, 'weight_kg' => 1, 'average_weight_kg' => 1, 'sample_size' => 1]);
    expect($both['status'])->toBe('rejected')->and($both['error']['code'])->toBe('invalid_payload');
});

it('records the feed a batch or a pig ate, drawn from a store when one is named', function () {
    $batch = openBatch();
    $item = stockItem('GROWER-MEAL', ['category' => InventoryCategory::FinishedFeed, 'feed_type_id' => growerFeed()->id]);
    $store = store('FEED');
    receiveStock($item, '500', 30000, $store);

    $a = quick('record_feed', ['batch' => $batch->code, 'feed_type_id' => growerFeed()->id, 'quantity_kg' => 120, 'inventory_location_id' => $store->id]);

    expect($a)->toMatchArray(['status' => 'accepted', 'server_type' => 'feed_consumption_record'])->and((string) $batch->feedRecords()->first()->quantity_kg)->toBe('120.00')->and($batch->feedRecords()->first()->cost_minor)->toBe(3600000);

    $tooMuch = quick('record_feed', ['batch' => $batch->code, 'feed_type_id' => growerFeed()->id, 'quantity_kg' => 9000, 'inventory_location_id' => $store->id]);
    expect($tooMuch['status'])->toBeIn(['rejected', 'conflict'])->and($batch->feedRecords()->count())->toBe(1);
});

it('records a treatment and a vaccination, and a withdrawal period starts', function () {
    $pig = register();
    $drug = medicine(7);
    $vac = vaccine();

    $t = quick('record_treatment', ['animal' => $pig->animal_number, 'medicine_id' => $drug->id, 'dose' => 5, 'dose_unit' => 'ml', 'route' => 'im']);
    $v = quick('record_vaccination', ['animal' => $pig->animal_number, 'medicine_id' => $vac->id]);

    expect($t)->toMatchArray(['status' => 'accepted', 'server_type' => 'treatment'])->and($v)->toMatchArray(['status' => 'accepted', 'server_type' => 'vaccination'])
        ->and($pig->treatments()->count())->toBe(1)->and($pig->vaccinations()->count())->toBe(1)->and($pig->withdrawalPeriods()->count())->toBe(1);

    $notVaccine = quick('record_vaccination', ['animal' => $pig->animal_number, 'medicine_id' => $drug->id]);
    expect($notVaccine['status'])->toBe('rejected')->and($notVaccine['error']['code'])->toBe('not_a_vaccine');
});

it('records the death of a pig, or of pigs in a batch', function () {
    $pig = register();
    $batch = openBatch(['count' => 50]);
    $cause = lookup(LookupCategory::MortalityCause, 'scours');

    $a = quick('record_mortality', ['animal' => $pig->animal_number, 'cause_id' => $cause]);
    $b = quick('record_mortality', ['batch' => $batch->code, 'count' => 2, 'cause_id' => $cause]);

    expect($a)->toMatchArray(['status' => 'accepted', 'server_type' => 'mortality_record'])->and($pig->fresh()->status->value)->toBe('dead')
        ->and($b['status'])->toBe('accepted')->and($batch->headCount())->toBe(48);

    // Recording the same pig's death again from another device is a conflict: it is already dead.
    $again = quick('record_mortality', ['animal' => $pig->animal_number, 'cause_id' => $cause]);
    expect($again['status'])->toBe('conflict')->and($again['error']['code'])->toBe('animal_not_active');
});

it('moves a pig to another pen', function () {
    $a = newPen('PEN-A');
    $b = newPen('PEN-B');
    $pig = register(['pen_id' => $a->id]);

    $moved = quick('move_pigs', ['animal' => $pig->animal_number, 'pen_id' => $b->id, 'reason_id' => lookup(LookupCategory::MovementReason, 'overcrowding')], null, now()->toIso8601String());

    expect($moved)->toMatchArray(['status' => 'accepted', 'server_type' => 'animal_movement'])->and($pig->fresh()->current_pen_id)->toBe($b->id);

    $same = quick('move_pigs', ['animal' => $pig->animal_number, 'pen_id' => $b->id], null, now()->toIso8601String());
    expect($same['status'])->toBe('conflict')->and($same['error']['code'])->toBe('movement_same_place');

    $nowhere = quick('move_pigs', ['animal' => $pig->animal_number]);
    expect($nowhere['status'])->toBe('rejected')->and($nowhere['error']['fields'])->toHaveKeys(['pen_id', 'location_id']);
});

it('records a service, and refuses it while the sow is pregnant', function () {
    $sow = register();
    $boar = boar();

    $a = quick('record_service', ['sow' => $sow->animal_number, 'method' => 'natural', 'boar' => $boar->animal_number], null, now()->subDays(30)->toIso8601String());

    expect($a)->toMatchArray(['status' => 'accepted', 'server_type' => 'breeding_service'])->and(BreedingService::where('sow_id', $sow->id)->count())->toBe(1);

    $b = quick('record_service', ['sow' => $sow->animal_number, 'method' => 'natural', 'boar' => $boar->animal_number], null, now()->subDays(40)->toIso8601String());
    expect($b['status'])->toBe('conflict')->and($b['error']['code'])->toBe('service_out_of_order')->and(BreedingService::where('sow_id', $sow->id)->count())->toBe(1);

    $bad = quick('record_service', ['sow' => $sow->animal_number, 'method' => 'wish']);
    expect($bad['status'])->toBe('rejected')->and($bad['error']['fields'])->toHaveKey('method');
});

it('records a farrowing once even if the device sends it twice', function () {
    $sow = register();
    serve($sow);
    $m = mutation('record_farrowing', ['sow' => $sow->animal_number, 'total_born' => 12, 'born_alive' => 10, 'stillborn' => 1, 'mummified' => 1, 'assisted' => false], ['occurred_at' => now()->subHour()->toIso8601String()]);

    $first = push([$m], $this->headers)->json(FIRST_RESULT);
    $second = push([$m], $this->headers)->json(FIRST_RESULT);

    expect($first)->toMatchArray(['status' => 'accepted', 'server_type' => 'litter'])->and($second)->toMatchArray(['status' => 'accepted', 'replayed' => true, 'server_id' => $first['server_id']])
        ->and(Litter::count())->toBe(1)->and(Farrowing::where('sow_id', $sow->id)->count())->toBe(1);

    // The same farrowing sent as a new mutation (a second device) is a duplicate, reported as a conflict.
    $dup = quick('record_farrowing', ['sow' => $sow->animal_number, 'total_born' => 12, 'born_alive' => 10, 'stillborn' => 1, 'mummified' => 1], null, now()->subHour()->toIso8601String());
    expect($dup['status'])->toBe('conflict')->and($dup['error']['code'])->toBe('sow_lactating')->and(Litter::count())->toBe(1);
});

it('weans a litter, and says so when it is already weaned', function () {
    $sow = register();
    serve($sow, 140);
    $litter = farrow($sow, ['farrowed_on' => now()->subDays(28)->startOfDay()]);

    $a = quick('record_weaning', ['litter' => $litter->litter_number, 'weaned_count' => 9, 'total_weight_kg' => 72.5]);

    expect($a)->toMatchArray(['status' => 'accepted', 'server_type' => 'weaning_record'])->and($litter->fresh()->weaning->weaned_count)->toBe(9);

    $again = quick('record_weaning', ['litter' => $litter->litter_number, 'weaned_count' => 9]);
    expect($again['status'])->toBe('conflict')->and($again['error']['code'])->toBe('litter_weaned');
});

it('submits a stock count, and reports a count made on stale numbers as a conflict leaving nothing behind', function () {
    $item = stockItem('MAIZE');
    $store = store('RAW');
    receiveStock($item, '100', 35000, $store);

    $unexplained = quick('stock_count', ['location_id' => $store->id, 'lines' => [['item_id' => $item->id, 'counted_quantity' => 95, 'seen_quantity' => 100]]]);
    expect($unexplained['status'])->toBe('rejected')->and(StockCount::count())->toBe(0);          // a variance needs a reason, and nothing was left half-done

    $ok = quick('stock_count', ['location_id' => $store->id, 'lines' => [['item_id' => $item->id, 'counted_quantity' => 95, 'seen_quantity' => 100, 'reason' => 'Spillage']]]);
    expect($ok)->toMatchArray(['status' => 'accepted', 'server_type' => 'stock_count'])->and(StockCount::sole()->status->value)->toBe('submitted');

    // Someone else counted/issued in between: the device's 100 is no longer what the system holds.
    issueStock($item, '20', $store);
    app(RejectStockCount::class)(StockCount::sole(), owner(), 'Count again');
    $stale = quick('stock_count', ['location_id' => $store->id, 'lines' => [['item_id' => $item->id, 'counted_quantity' => 80, 'seen_quantity' => 100]]]);

    expect($stale['status'])->toBe('conflict')->and($stale['error']['code'])->toBe('stale_count')->and(StockCount::where('status', 'draft')->count())->toBe(0);
});

it('completes a task, for the person it was given to', function () {
    $mine = newTask(['title' => 'Check the troughs', 'assignee' => $this->worker]);
    $theirs = newTask(['title' => 'Not mine', 'assignee' => farmWorker()]);
    $field = mobileWorker('Farm Worker');
    $fieldTask = newTask(['title' => 'Field task', 'assignee' => $field]);

    $done = quick('complete_task', ['task' => $mine->number, 'notes' => 'All clear']);

    expect($done)->toMatchArray(['status' => 'accepted', 'server_type' => 'task'])->and($mine->fresh()->status)->toBe(TaskStatus::Done)->and($mine->fresh()->completion_notes)->toBe('All clear');

    // A supervisor may close anyone's task; a field worker only their own.
    $fieldHeaders = mobileHeaders($field, FIELD_DEVICE);
    $own = push([mutation('complete_task', ['task' => $fieldTask->number])], $fieldHeaders, FIELD_DEVICE)->json(FIRST_RESULT);
    $notOwn = push([mutation('complete_task', ['task' => $theirs->number])], $fieldHeaders, FIELD_DEVICE)->json(FIRST_RESULT);
    $gone = quick('complete_task', ['task' => 'TK-999999']);

    expect($own['status'])->toBe('accepted')->and($notOwn['status'])->toBe('rejected')->and($notOwn['error']['code'])->toBe('task_forbidden')->and($theirs->fresh()->status)->toBe(TaskStatus::Open)
        ->and($gone['status'])->toBe('rejected')->and($gone['error']['code'])->toBe('not_found');
});

it('works through a day offline: a queue sent late, partly twice, in one go', function () {
    $pig = register();
    $other = register();
    $batch = openBatch();
    $queue = [
        mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 30], ['occurred_at' => now()->subHours(8)->toIso8601String()]),
        mutation('record_treatment', ['animal' => $pig->animal_number, 'medicine_id' => medicine()->id], ['occurred_at' => now()->subHours(7)->toIso8601String()]),
        mutation('record_feed', ['batch' => $batch->code, 'feed_type_id' => growerFeed()->id, 'quantity_kg' => 50], ['occurred_at' => now()->subHours(6)->toIso8601String()]),
        mutation('record_mortality', ['animal' => $other->animal_number, 'cause_id' => lookup(LookupCategory::MortalityCause, 'crushed')], ['occurred_at' => now()->subHours(5)->toIso8601String()]),
        mutation('record_weight', ['animal' => $other->animal_number, 'weight_kg' => 12], ['occurred_at' => now()->subHours(4)->toIso8601String()]),   // after its death: a conflict
    ];

    // The connection drops after the first two reach the server; the app resends the whole queue.
    push(array_slice($queue, 0, 2), $this->headers)->assertOk();
    $all = push($queue, $this->headers)->assertOk()->json('results');

    expect(collect($all)->pluck('status')->all())->toBe(['accepted', 'accepted', 'accepted', 'accepted', 'conflict'])->and(collect($all)->pluck('replayed')->all())->toBe([true, true, false, false, false])
        ->and($pig->weights()->count())->toBe(1)->and($pig->treatments()->count())->toBe(1)->and($batch->feedRecords()->count())->toBe(1)->and($other->weights()->count())->toBe(0);

    // And again after a restart: nothing more happens, the same five answers come back.
    $replay = push($queue, $this->headers)->json('results');
    expect(collect($replay)->pluck('status')->all())->toBe(['accepted', 'accepted', 'accepted', 'accepted', 'conflict'])->and(collect($replay)->every(fn ($r) => $r['replayed']))->toBeTrue()
        ->and(SyncMutation::count())->toBe(5)->and(SyncMutation::pluck('attempts')->unique()->all())->toBe([1]);
});

it('gives every quick action a permission, so a field worker cannot reach what their role does not allow', function () {
    $store = mobileWorker('Store Officer');
    $headers = mobileHeaders($store, 'device-s1');
    $pig = register();

    foreach (['record_treatment', 'record_vaccination', 'record_service', 'record_farrowing', 'record_weaning'] as $type) {
        $r = push([mutation($type, ['animal' => $pig->animal_number])], $headers, 'device-s1')->json(FIRST_RESULT);

        expect($r['status'])->toBe('rejected')->and($r['error']['code'])->toBe('forbidden');
    }

    expect(Str::of(SyncMutation::count())->toString())->toBe('5');
});

it('stores a mutation once even when the same client id arrives together in two requests', function () {
    $pig = register();
    $worker = $this->worker;
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 77]);
    $process = app(ProcessMutation::class);

    // Another request stores the same client id between this one's validation and its claim.
    SyncMutation::query()->insert([
        'client_id' => $m['client_id'], 'device_id' => MOBILE_DEVICE, 'user_id' => $worker->id, 'type' => 'record_weight', 'occurred_at' => now()->subMinutes(5), 'payload' => json_encode($m['payload']),
        'status' => 'accepted', 'server_type' => 'weight_record', 'server_id' => 5, 'attempted_at' => now(), 'synced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $result = $process($worker, MOBILE_DEVICE, $m);

    expect($result)->toMatchArray(['status' => 'accepted', 'replayed' => true, 'server_id' => 5])->and($pig->weights()->count())->toBe(0)->and(SyncMutation::count())->toBe(1);
});
