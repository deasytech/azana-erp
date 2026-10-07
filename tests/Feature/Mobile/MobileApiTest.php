<?php

use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Mobile\Models\SyncMutation;
use App\Enums\LookupCategory;
use App\Models\User;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

it('signs a worker in with a token for the device, and shows who they are', function () {
    $worker = mobileWorker();

    $login = mobileLogin($worker)->assertOk()->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'roles', 'permissions']]);

    expect($login->json('user.roles'))->toBe(['Farm Worker'])->and($login->json('user.permissions'))->toContain('animals.create', 'health.create')->not->toContain('finance.view')
        ->and($worker->tokens()->count())->toBe(1)->and($worker->tokens()->first()->name)->toBe('mobile:device-a1');

    apiGet(API_ME, bearer($login->json('token')))->assertOk()->assertJsonPath('email', $worker->email);

    mobileLogin($worker)->assertOk();                                          // signing in again on the same device replaces its token
    expect($worker->tokens()->count())->toBe(1);

    mobileLogin($worker, 'device-b2')->assertOk();
    expect($worker->tokens()->count())->toBe(2);
});

it('turns away wrong passwords, unknown people, inactive users and people without mobile access, with no hint which', function () {
    $worker = mobileWorker();

    mobileLogin($worker, password: 'wrong')->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
    $this->postJson(API_LOGIN, ['email' => 'nobody@azana.test', 'password' => 'x', 'device_id' => 'd'])->assertStatus(401)->assertJsonPath('code', 'invalid_credentials');
    $this->postJson(API_LOGIN, ['email' => $worker->email])->assertStatus(422);

    $worker->update(['is_active' => false]);
    mobileLogin($worker)->assertForbidden()->assertJsonPath('code', 'mobile_forbidden');

    mobileLogin(mobileWorker('Accountant'))->assertForbidden();               // the accountant has no mobile permission (and needs two-factor)
    expect(SyncMutation::count())->toBe(0);
});

it('keeps roles that need two-factor sign-in on the web app', function () {
    $role = Role::create(['name' => 'Supervisor', 'guard_name' => 'web', 'requires_two_factor' => true])->givePermissionTo('mobile.view');
    $user = User::factory()->create(['password' => Hash::make(MOBILE_PASSWORD)]);
    $user->assignRole($role);

    mobileLogin($user)->assertForbidden()->assertJsonPath('code', 'two_factor_required');
});

it('locks out a token the moment the mobile permission or the account is withdrawn, and on sign-out', function () {
    $worker = mobileWorker();
    $headers = mobileHeaders($worker);

    apiGet(API_ME, $headers)->assertOk();

    $worker->update(['is_active' => false]);
    apiGet(API_ME, $headers)->assertForbidden();
    $worker->update(['is_active' => true]);
    apiGet(API_ME, $headers)->assertOk();

    apiPost('/api/v1/auth/logout', [], $headers)->assertOk();
    apiGet(API_ME, $headers)->assertUnauthorized();
    apiGet(API_ME)->assertUnauthorized();
});

it('refuses a token that is not for the mobile app and an expired one', function () {
    $worker = mobileWorker();
    $other = $worker->createToken('something-else', ['other'])->plainTextToken;
    $expired = $worker->createToken('mobile:old', ['mobile'], now()->subMinute())->plainTextToken;

    apiGet(API_ME, bearer($other))->assertForbidden();
    apiGet(API_ME, bearer($expired))->assertUnauthorized();
});

it('limits sign-in attempts', function () {
    $worker = mobileWorker();

    collect(range(1, 5))->each(fn () => mobileLogin($worker, password: 'wrong')->assertStatus(401));

    mobileLogin($worker, password: 'wrong')->assertStatus(429);
});

it('limits sign-in per account whatever the address, and per address whatever the account', function () {
    $worker = mobileWorker();
    $from = fn (string $ip) => test()->withServerVariables(['REMOTE_ADDR' => $ip]);

    // One account, a new address each time: the account's own limit still runs out.
    foreach (range(1, 5) as $i) {
        $from("10.0.0.{$i}")->postJson(API_LOGIN, ['email' => $worker->email, 'password' => 'wrong', 'device_id' => 'd'])->assertStatus(401);
    }

    $from('10.0.0.99')->postJson(API_LOGIN, ['email' => strtoupper($worker->email), 'password' => MOBILE_PASSWORD, 'device_id' => 'd'])->assertStatus(429);

    // One address trying many accounts: the address's limit runs out (20), though no account has had more than one attempt.
    foreach (range(1, 20) as $n) {
        $from('10.9.9.9')->postJson(API_LOGIN, ['email' => "person{$n}@azana.test", 'password' => 'x', 'device_id' => 'd'])->assertStatus(401);
    }

    $from('10.9.9.9')->postJson(API_LOGIN, ['email' => 'person21@azana.test', 'password' => 'x', 'device_id' => 'd'])->assertStatus(429);
    $from('10.8.8.8')->postJson(API_LOGIN, ['email' => 'person21@azana.test', 'password' => 'x', 'device_id' => 'd'])->assertStatus(401);   // another address is fine
});

it('says what a scanned code is, only to people who may see it', function () {
    $animal = register();
    $pen = newPen('PEN-9');
    $batch = openBatch(['name' => 'Grower 1']);
    $headers = mobileHeaders(mobileWorker());

    apiGet(API_SCAN.$animal->animal_number, $headers)->assertOk()->assertJson(['type' => 'animal', 'id' => $animal->id, 'code' => $animal->animal_number]);
    apiGet(API_SCAN.$animal->public_id, $headers)->assertOk()->assertJsonPath('type', 'animal');
    apiGet(API_SCAN.urlencode(route('animals.lookup', $animal->public_id)), $headers)->assertOk()->assertJsonPath('id', $animal->id);
    apiGet(API_SCAN.'PEN-9', $headers)->assertOk()->assertJson(['type' => 'pen', 'id' => $pen->id]);
    apiGet(API_SCAN.$batch->code, $headers)->assertOk()->assertJson(['type' => 'production_batch', 'id' => $batch->id]);
    apiGet(API_SCAN.'NOPE-1', $headers)->assertNotFound();

    $animalSummary = apiGet('/api/v1/animals/'.$animal->animal_number, $headers)->assertOk();
    expect($animalSummary->json())->toHaveKeys(['animal_number', 'public_id', 'sex', 'category', 'status', 'position', 'latest_weight_kg']);
    apiGet('/api/v1/animals/NOPE-1', $headers)->assertNotFound();

    // A role with no animal permission sees no animals.
    $role = Role::create(['name' => 'Gate keeper', 'guard_name' => 'web'])->givePermissionTo('mobile.view');
    $keeper = User::factory()->create(['password' => Hash::make(MOBILE_PASSWORD)]);
    $keeper->assignRole($role);
    apiGet(API_SCAN.$animal->animal_number, mobileHeaders($keeper))->assertNotFound();
});

it('gives the lists to keep offline, with a version that says when nothing changed', function () {
    newPen('PEN-1');
    $headers = mobileHeaders(mobileWorker());

    $first = apiGet('/api/v1/reference', $headers)->assertOk();
    $data = $first->json('data');

    expect($data['quick_actions'])->toHaveCount(12)->and(collect($data['pens'])->pluck('code')->all())->toContain('PEN-1')->and(collect($data['mortality_causes'])->pluck('code')->all())->toContain('scours')
        ->and($data['service_methods'])->toBe(['natural', 'artificial_insemination']);

    apiGet('/api/v1/reference?version='.$first->json('version'), $headers)->assertOk()->assertJson(['version' => $first->json('version'), 'unchanged' => true])->assertJsonMissingPath('data');

    newPen('PEN-2');
    apiGet('/api/v1/reference?version='.$first->json('version'), $headers)->assertOk()->assertJsonPath('unchanged', null)->assertJsonPath('data.pens.1.code', 'PEN-2');
});

it('lists the worker\'s own open tasks', function () {
    $worker = mobileWorker();
    $mine = newTask(['title' => 'Check the troughs', 'assignee' => $worker, 'dueOn' => now()->subDay()]);
    newTask(['title' => 'Someone else', 'assignee' => farmWorker()]);

    $tasks = apiGet('/api/v1/tasks', mobileHeaders($worker))->assertOk()->json('data');

    expect($tasks)->toHaveCount(1)->and($tasks[0])->toMatchArray(['number' => $mine->number, 'title' => 'Check the troughs', 'overdue' => true]);
});

it('accepts a quick action once, however many times the device sends it', function () {
    $pig = register();
    $headers = mobileHeaders(mobileWorker());
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 82.5]);

    $first = push([$m], $headers)->assertOk()->json(FIRST_RESULT);
    $again = push([$m], $headers)->assertOk()->json(FIRST_RESULT);
    $third = apiPost('/api/v1/quick/record_weight', ['device_id' => MOBILE_DEVICE] + Arr::only($m, ['client_id', 'occurred_at', 'payload']), $headers);

    expect($first)->toMatchArray(['status' => 'accepted', 'replayed' => false, 'server_type' => 'weight_record'])->and($first['server_id'])->toBeInt()
        ->and($again)->toMatchArray(['status' => 'accepted', 'replayed' => true, 'server_id' => $first['server_id']])
        ->and($pig->weights()->count())->toBe(1)->and(SyncMutation::count())->toBe(1)->and(SyncMutation::sole()->synced_at)->not->toBeNull();

    $third->assertOk()->assertJsonPath('replayed', true);
});

it('does the action with the time it happened on the device, not the time it arrived', function () {
    $pig = register();
    $then = now()->subHours(6)->startOfMinute();

    push([mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 40], ['occurred_at' => $then->toIso8601String()])], mobileHeaders(mobileWorker()))->assertOk();

    expect($pig->weights()->first()->weighed_at->equalTo($then))->toBeTrue();
});

it('processes a queue in order and gives each its own outcome, so one bad entry never blocks the rest', function () {
    $pig = register();
    $good = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 60]);
    $bad = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 'heavy']);
    $unknown = mutation('fly_the_pig', ['animal' => $pig->animal_number]);
    $later = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 61], ['occurred_at' => now()->subMinute()->toIso8601String()]);

    $results = push([$good, $bad, $unknown, $later], mobileHeaders(mobileWorker()))->assertOk()->json('results');

    expect(collect($results)->pluck('status')->all())->toBe(['accepted', 'rejected', 'rejected', 'accepted'])
        ->and($results[1]['error'])->toMatchArray(['code' => 'invalid_payload'])->and($results[1]['error']['fields'])->toHaveKey('weight_kg')
        ->and($results[2]['error']['code'])->toBe('unknown_type')->and($pig->weights()->count())->toBe(2);
});

it('rejects a mutation the person is not allowed to make, without doing it', function () {
    $pig = register();
    $store = mobileWorker('Store Officer');                                   // may not record animal health

    $result = push([mutation('record_treatment', ['animal' => $pig->animal_number, 'medicine_id' => medicine()->id])], mobileHeaders($store))->assertOk()->json(FIRST_RESULT);

    expect($result['status'])->toBe('rejected')->and($result['error']['code'])->toBe('forbidden')->and($pig->treatments()->count())->toBe(0);
});

it('refuses a mutation with a bad envelope, and one dated in the future', function () {
    $headers = mobileHeaders(mobileWorker());

    $noId = push([['type' => 'record_weight', 'occurred_at' => now()->toIso8601String(), 'payload' => []]], $headers)->json(FIRST_RESULT);
    $future = push([mutation('record_weight', [], ['occurred_at' => now()->addDay()->toIso8601String()])], $headers)->json(FIRST_RESULT);

    expect($noId['status'])->toBe('rejected')->and($noId['error']['code'])->toBe('invalid_envelope')->and($future['error']['code'])->toBe('invalid_envelope')->and(SyncMutation::count())->toBe(0);
    push([], $headers)->assertStatus(422);
    push(array_fill(0, 101, mutation('record_weight', [])), $headers)->assertStatus(422);
});

it('does not let one device replay another person\'s or device\'s client id', function () {
    $pig = register();
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 50]);
    push([$m], mobileHeaders(mobileWorker()))->assertOk();

    $theirs = push([$m], mobileHeaders(mobileWorker(), 'device-c3'), 'device-c3')->json(FIRST_RESULT);

    expect($theirs['status'])->toBe('rejected')->and($theirs['error']['code'])->toBe('idempotency_conflict')->and($pig->weights()->count())->toBe(1);
});

it('reports a conflict, never silently overwriting, when the server has moved on', function () {
    $pig = register();
    $headers = mobileHeaders(mobileWorker());
    app(RecordMortality::class)($pig, now()->subDay(), lookup(LookupCategory::MortalityCause, 'scours'));   // someone recorded its death at the farm

    $result = push([mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 70])], $headers)->assertOk()->json(FIRST_RESULT);

    expect($result['status'])->toBe('conflict')->and($result['error']['code'])->toBe('animal_not_active')->and($pig->weights()->count())->toBe(0);

    apiGet('/api/v1/sync/mutations/'.$result['client_id'], $headers)->assertOk()->assertJsonPath('status', 'conflict');
    apiPost('/api/v1/quick/record_weight', ['device_id' => MOBILE_DEVICE, 'client_id' => (string) Str::uuid(), 'occurred_at' => now()->subMinute()->toIso8601String(), 'payload' => ['animal' => $pig->animal_number, 'weight_kg' => 70]], $headers)->assertStatus(409);
});

it('gives a conflict the same answer when sent again, and shows it in the sync status', function () {
    $pig = register();
    $headers = mobileHeaders(mobileWorker());
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 70]);
    app(RecordMortality::class)($pig, now()->subDay(), lookup(LookupCategory::MortalityCause, 'scours'));

    push([$m], $headers);
    $again = push([$m], $headers)->json(FIRST_RESULT);
    push([mutation('record_weight', ['animal' => 'ZZZ-0'])], $headers);
    $ok = register();
    push([mutation('record_weight', ['animal' => $ok->animal_number, 'weight_kg' => 55])], $headers);

    $status = apiGet('/api/v1/sync/status?device_id='.MOBILE_DEVICE, $headers)->assertOk()->json();

    expect($again)->toMatchArray(['status' => 'conflict', 'replayed' => true])->and($status['counts'])->toMatchArray(['accepted' => 1, 'conflict' => 1, 'rejected' => 1, 'failed' => 0])
        ->and($status['last_synced_at'])->not->toBeNull()->and($status['needs_attention'])->toHaveCount(2)->and($status['needs_attention'][1]['code'])->toBe('animal_not_active');

    apiGet('/api/v1/sync/status', $headers)->assertStatus(422);
    apiGet('/api/v1/sync/status?device_id=another', $headers)->assertOk()->assertJsonPath('counts.accepted', 0);
});

it('only lets a person read their own mutations', function () {
    $pig = register();
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 50]);
    push([$m], mobileHeaders(mobileWorker()));

    apiGet('/api/v1/sync/mutations/'.$m['client_id'], mobileHeaders(mobileWorker(), 'device-z'))->assertNotFound();
});

it('lets a mutation that failed on the server be sent again, and runs it then', function () {
    $pig = register();
    $worker = mobileWorker();
    $m = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 90]);
    // What a first attempt that hit a server error leaves behind: the mutation, marked failed, nothing recorded.
    SyncMutation::create(['client_id' => $m['client_id'], 'device_id' => MOBILE_DEVICE, 'user_id' => $worker->id, 'type' => 'record_weight', 'occurred_at' => now()->subHour(), 'payload' => $m['payload'],
        'status' => 'failed', 'error_code' => 'server_error', 'attempted_at' => now()->subMinutes(30)]);

    $second = push([$m], mobileHeaders($worker))->json(FIRST_RESULT);

    expect($second)->toMatchArray(['status' => 'accepted', 'replayed' => false])->and($pig->weights()->count())->toBe(1)->and(SyncMutation::sole())->attempts->toBe(2)->error_code->toBeNull();
});
