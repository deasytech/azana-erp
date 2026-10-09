<?php

use App\Domain\Backup\Actions\GetOperationsStatus;
use App\Domain\Backup\Data\StatusCheck as C;
use App\Support\ErrorTally;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function errorStatus(): C
{
    return collect(app(GetOperationsStatus::class)())->firstWhere('name', 'Application errors');
}

it('counts the errors the application reports and shows where the latest one happened, without its message', function () {
    expect(errorStatus()->state)->toBe(C::OK)->and(errorStatus()->detail)->toContain('No unexpected errors');

    report(new RuntimeException('customer@example.com owes 500'));

    expect(ErrorTally::lastDay()['count'])->toBe(1)->and(errorStatus()->state)->toBe(C::OK)
        ->and(errorStatus()->detail)->toContain('RuntimeException in tests/Feature/Hardening/ErrorMonitoringTest.php')->not->toContain('customer@example.com');
});

it('warns when errors pile up and fails when they flood', function () {
    config(['backup.max_errors_per_day' => 3]);

    array_map(fn () => report(new RuntimeException('boom')), range(1, 4));

    expect(errorStatus()->state)->toBe(C::WARNING);

    array_map(fn () => report(new RuntimeException('boom')), range(1, 27));

    expect(errorStatus()->state)->toBe(C::FAILED);
});

it('forgets errors older than a day', function () {
    report(new RuntimeException('old'));
    $this->travel(25)->hours();

    expect(ErrorTally::lastDay()['count'])->toBe(0);
});

it('does not count the errors a user causes by mistake, such as a failed validation', function () {
    $this->actingAs(owner())->postJson('/api/v1/sync/push', [])->assertStatus(422);

    expect(ErrorTally::lastDay()['count'])->toBe(0);
});
