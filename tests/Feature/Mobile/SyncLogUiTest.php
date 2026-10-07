<?php

use App\Domain\Health\Actions\RecordMortality;
use App\Domain\Mobile\Models\SyncMutation;
use App\Enums\LookupCategory;
use App\Filament\Resources\SyncMutations\Pages\ListSyncMutations;
use App\Filament\Resources\SyncMutations\SyncMutationResource;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, MasterDataSeeder::class, FinanceSeeder::class]);
    Filament::setCurrentPanel('admin');
});

/** A conflict and an accepted record, as the API would leave them. */
function syncLog(): array
{
    $worker = mobileWorker();
    $pig = register();
    app(RecordMortality::class)($pig, now()->subDay(), lookup(LookupCategory::MortalityCause, 'scours'));
    $headers = mobileHeaders($worker);
    $conflict = push([mutation('record_weight', ['animal' => $pig->animal_number, 'weight_kg' => 50])], $headers)->json('results.0.client_id');
    $fine = push([mutation('record_weight', ['animal' => register()->animal_number, 'weight_kg' => 50])], $headers)->json('results.0.client_id');

    return [SyncMutation::firstWhere('client_id', $conflict), SyncMutation::firstWhere('client_id', $fine)];
}

it('shows a supervisor the conflicts waiting for review and lets them mark one reviewed', function () {
    [$conflict, $fine] = syncLog();
    $this->actingAs(userWithRole('Farm Manager'));

    Livewire::test(ListSyncMutations::class)->assertCanSeeTableRecords([$conflict])->assertCanNotSeeTableRecords([$fine])
        ->callAction(TestAction::make('review')->table($conflict), ['note' => 'Pig was already dead; weight not needed'])->assertNotified('Marked as reviewed')
        ->assertCanNotSeeTableRecords([$conflict]);

    expect($conflict->fresh())->reviewed_at->not->toBeNull()->review_note->toContain('already dead')->and($conflict->fresh()->reviewed_by)->not->toBeNull();

    Livewire::test(ListSyncMutations::class)->set('activeTab', 'all')->assertCanSeeTableRecords([$conflict, $fine])->assertActionHidden(TestAction::make('review')->table($fine));
    $this->get(SyncMutationResource::getUrl('view', ['record' => $conflict]))->assertSuccessful()->assertSee('animal_not_active')->assertSee('weight_kg');
});

it('keeps the sync log from field workers and from creating or deleting rows', function () {
    [$conflict] = syncLog();

    $this->actingAs(farmWorker());
    $this->get(SyncMutationResource::getUrl('index'))->assertForbidden();
    $this->get(SyncMutationResource::getUrl('view', ['record' => $conflict]))->assertForbidden();

    $owner = owner();
    $this->actingAs($owner);
    expect($owner->can('delete', $conflict))->toBeFalse()->and($owner->can('create', SyncMutation::class))->toBeFalse();
});
