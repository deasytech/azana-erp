<?php

use App\Models\User;
use App\Support\UserGuide\Guide;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

const GUIDE_URL = '/user/documents';
const GUIDE_ROLE = 'Farm Worker';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('keeps the user guide behind the sign-in', function () {
    $this->get(GUIDE_URL)->assertRedirect();
});

it('shows the user guide to a signed-in user, with a section for every sidebar entry', function () {
    $user = User::factory()->create()->assignRole(GUIDE_ROLE);

    $html = $this->actingAs($user)->get(GUIDE_URL)->assertOk()->assertSee('Azana Farms')->getContent();

    foreach (Guide::nav() as $items) {
        foreach ($items as [$id]) {
            expect($html)->toContain('id="'.$id.'"');
        }
    }
});

it('lists every standard role in the guide', function () {
    $user = User::factory()->create()->assignRole(GUIDE_ROLE);

    $response = $this->actingAs($user)->get(GUIDE_URL);

    foreach (Guide::roles() as $role) {
        $response->assertSee($role['name']);
    }
});

it('puts the user guide last in the ERP sidebar', function () {
    $user = User::factory()->create()->assignRole(GUIDE_ROLE);
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');

    $groups = collect(Filament::getNavigation());

    expect($groups->last()->getLabel())->toBe('Help')
        ->and(collect($groups->last()->getItems())->first()->getUrl())->toBe(route('user.documents'));
});
