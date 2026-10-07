<?php

use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Mobile\Services\QuickCatalogue;
use App\Enums\LookupCategory;
use App\Support\ApiDocs\ApiReference;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
});

/** The names a route or documented path uses for its parameters do not matter: {code} and {client_id} are both just "a parameter". */
function plainPath(string $path): string
{
    return preg_replace('/\{[^}]+\}/', '{}', $path);
}

/** The structure of a JSON value without its values: keys of objects, and the shape of a list's first item. */
function shapeOf(mixed $value): mixed
{
    if (! is_array($value)) {
        return null;
    }

    if (array_is_list($value)) {
        return $value === [] ? [] : ['[]' => shapeOf($value[0])];
    }

    return collect($value)->map(fn ($v) => shapeOf($v))->sortKeys()->all();
}

/** Both have the same keys, all the way down (a list that is empty on one side is not compared deeper). */
function sameShape(mixed $documented, mixed $actual): bool
{
    $bothLists = isset($documented['[]']) || isset($actual['[]']);

    $same = match (true) {
        ! is_array($documented) || ! is_array($actual) => ! is_array($documented) === ! is_array($actual) || $documented === null || $actual === null,
        $documented === [] || $actual === [] => true,
        $bothLists => isset($documented['[]'], $actual['[]']) && sameShape($documented['[]'], $actual['[]']),
        default => array_keys($documented) === array_keys($actual) && collect($documented)->every(fn ($shape, $key) => sameShape($shape, $actual[$key])),
    };

    return $same;
}

/** The documented example response with that label of an endpoint. */
function documented(string $method, string $path, string $label = '200 Response'): array
{
    $endpoint = collect(ApiReference::groups())->pluck('endpoints')->flatten(1)->first(fn ($e) => $e['method'] === $method && $e['path'] === $path);

    return shapeOf(collect($endpoint['responses'])->first(fn ($r) => $r[0] === $label)[1]);
}

it('serves the reference to anyone, with every endpoint and quick action listed', function () {
    $page = $this->get('/docs')->assertOk()->assertSee('Azana Farms API')->assertSee('Offline and idempotency')->assertSee('Mutation outcomes');

    foreach (ApiReference::signatures() as $signature) {
        [$method, $path] = explode(' ', $signature, 2);
        $page->assertSee($path, false)->assertSee(">{$method}<", false);
    }

    foreach (app(QuickCatalogue::class)->types() as $type) {
        $page->assertSee('/quick/'.$type, false);
    }

    $page->assertSee('class="copy-btn"', false)->assertSee('tok-key', false)->assertSee('id="navSearch"', false)->assertSee('curl -X POST '.url('/api/v1/auth/login'), false);
});

it('documents exactly the endpoints the API has', function () {
    $real = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/'))
        ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))->map(fn ($m) => $m.' '.plainPath(Str::after($r->uri(), 'api/v1'))))->unique()->sort()->values()->all();
    $docs = collect(ApiReference::signatures())->map(fn ($s) => explode(' ', $s, 2))->map(fn ($p) => $p[0].' '.plainPath($p[1]))->unique()->sort()->values()->all();

    expect($docs)->toBe($real);
});

it('documents exactly the twelve quick actions, with every payload field and permission they use', function () {
    $quick = app(QuickCatalogue::class);
    $docs = collect(ApiReference::groups())->pluck('endpoints')->flatten(1)->filter(fn ($e) => isset($e['quick']))->keyBy('quick');

    expect($docs->keys()->sort()->values()->all())->toBe(collect($quick->types())->sort()->values()->all())->and($docs)->toHaveCount(12);

    foreach ($quick->types() as $type) {
        $documentedFields = collect($docs[$type]['params']['rows'])->map(fn ($r) => preg_replace('/(\[\]|\.).*$/', '', $r[0]))->unique()->sort()->values()->all();
        $realFields = collect(array_keys($quick->rules($type, [])))->map(fn ($f) => preg_replace('/\..*$/', '', $f))->unique()->sort()->values()->all();

        expect($documentedFields)->toBe($realFields, "payload of {$type}");

        // Each permission the action may ask for is named on its page.
        foreach ([[], ['batch' => 'X'], ['animal' => 'X']] as $payload) {
            expect($docs[$type]['permission'])->toContain($quick->permission($type, $payload));
        }
    }
});

it('shows example responses with the same fields the API really returns', function () {
    $worker = mobileWorker(FARM_MANAGER);
    $login = mobileLogin($worker)->assertOk();
    $headers = bearer($login->json('token'));
    $pig = register();
    newPen('PEN-1');
    newTask(['title' => 'Check', 'assignee' => $worker]);

    expect(sameShape(documented('POST', '/auth/login'), shapeOf($login->json())))->toBeTrue('login')
        ->and(sameShape(documented('GET', '/me'), shapeOf(apiGet(API_ME, $headers)->json())))->toBeTrue('me')
        ->and(sameShape(documented('GET', '/scan/{code}'), shapeOf(apiGet(API_SCAN.$pig->animal_number, $headers)->json())))->toBeTrue('scan')
        ->and(sameShape(documented('GET', '/animals/{code}'), shapeOf(apiGet('/api/v1/animals/'.$pig->animal_number, $headers)->json())))->toBeTrue('animal')
        ->and(sameShape(documented('GET', '/tasks'), shapeOf(apiGet('/api/v1/tasks', $headers)->json())))->toBeTrue('tasks');

    $reference = apiGet('/api/v1/reference', $headers)->json();
    expect(sameShape(documented('GET', '/reference'), shapeOf($reference)))->toBeTrue('reference')
        ->and(sameShape(documented('GET', '/reference', '200 Response (unchanged)'), shapeOf(apiGet('/api/v1/reference?version='.$reference['version'], $headers)->json())))->toBeTrue('reference unchanged');
});

it('shows example sync responses with the same fields the API really returns', function () {
    $worker = mobileWorker(FARM_MANAGER);
    $headers = mobileHeaders($worker);
    $pig = register();
    $dead = register();
    app(RecordMortality::class)($dead, now()->subDay(), lookup(LookupCategory::MortalityCause, 'scours'));
    $ok = mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 50]);
    $bad = mutation('record_weight', ['animal' => $dead->animal_number, 'weight_kg' => 50]);

    $push = push([$ok, $bad], $headers)->assertOk()->json();
    $single = apiPost(API_QUICK_WEIGHT, ['device_id' => MOBILE_DEVICE] + Arr::only(mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 51]), ['client_id', 'occurred_at', 'payload']), $headers);
    $conflict = apiPost(API_QUICK_WEIGHT, ['device_id' => MOBILE_DEVICE] + Arr::only(mutation('record_weight', ['animal' => $dead->animal_number, 'weight_kg' => 51]), ['client_id', 'occurred_at', 'payload']), $headers);
    $status = apiGet('/api/v1/sync/status?device_id='.MOBILE_DEVICE, $headers)->json();
    $one = apiGet('/api/v1/sync/mutations/'.$ok['client_id'], $headers)->json();

    expect(sameShape(documented('POST', '/sync/push'), shapeOf($push)))->toBeTrue('push')
        ->and(sameShape(documented('POST', '/quick/{type}', '201 Response'), shapeOf($single->json())))->toBeTrue('quick 201')
        ->and(sameShape(documented('POST', '/quick/{type}', '409 Response'), shapeOf($conflict->json())))->toBeTrue('quick 409')
        ->and(sameShape(documented('GET', '/sync/status'), shapeOf($status)))->toBeTrue('status')
        ->and(sameShape(documented('GET', '/sync/mutations/{client_id}'), shapeOf($one)))->toBeTrue('mutation')
        ->and($single->status())->toBe(201)->and($conflict->status())->toBe(409);
});

it('shows quick action examples that the API really accepts', function () {
    $headers = mobileHeaders(mobileWorker(FARM_MANAGER));
    // The weight example, exactly as printed (the animal it names must exist for it to be accepted).
    $endpoint = collect(ApiReference::groups())->pluck('endpoints')->flatten(1)->first(fn ($e) => ($e['quick'] ?? null) === 'record_weight');
    $sow = register();
    $payload = ['animal' => $sow->animal_number] + $endpoint['body']['payload'];
    $result = apiPost(API_QUICK_WEIGHT, ['client_id' => (string) Str::uuid()] + array_merge($endpoint['body'], ['payload' => $payload, 'occurred_at' => now()->subMinute()->toIso8601String()]), $headers);

    expect($result->status())->toBe(201)->and($sow->weights()->first()->weight_kg)->toBe('82.50');
});
