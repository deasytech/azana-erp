<?php

use App\Domain\Procurement\Actions\DecidePurchaseOrder;
use App\Filament\Pages\ApprovalInbox;
use App\Models\User;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class]);
    Filament::setCurrentPanel('admin');
});

it('counts what waits for approval in the sidebar and keeps the count for a minute', function () {
    $manager = userWithRole('Farm Manager');
    $this->actingAs($manager);
    expect(ApprovalInbox::getNavigationBadge())->toBeNull();

    // Raised by someone else, so it waits for this manager.
    $this->actingAs(userWithRole('Store Officer'));
    $order = purchaseOrder(approve: false);
    app(DecidePurchaseOrder::class)->submit($order);

    // Still the old answer: the count is cached.
    $this->actingAs($manager);
    expect(ApprovalInbox::getNavigationBadge())->toBeNull();

    Cache::flush();
    expect(ApprovalInbox::getNavigationBadge())->toBe('1');
});

it('shows no badge to someone who cannot open the page', function () {
    $this->actingAs(User::factory()->create());

    expect(ApprovalInbox::canAccess())->toBeFalse()->and(ApprovalInbox::getNavigationBadge())->toBeNull();
});
