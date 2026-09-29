<?php

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\LoginActivities\LoginActivityResource;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

it('seeds all twelve required roles', function () {
    expect(Role::pluck('name'))->toHaveCount(12)
        ->toContain('Owner/Director', 'Veterinarian', 'Farm Worker', 'Accountant');
});

it('shows each role only the modules it is permitted', function () {
    $owner = userWithRole('Owner/Director');
    $gm = userWithRole('General Manager');
    $worker = userWithRole('Farm Worker');

    $this->actingAs($owner);
    expect(UserResource::canViewAny())->toBeTrue()
        ->and(RoleResource::canViewAny())->toBeTrue()
        ->and(AuditLogResource::canViewAny())->toBeTrue();

    $this->actingAs($gm);
    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canCreate())->toBeFalse()
        ->and(RoleResource::canViewAny())->toBeFalse();

    $this->actingAs($worker);
    expect(UserResource::canViewAny())->toBeFalse()
        ->and(AuditLogResource::canViewAny())->toBeFalse()
        ->and(LoginActivityResource::canViewAny())->toBeFalse();
});

it('enforces each action of the permission matrix independently', function () {
    $role = Role::create(['name' => 'Tester']);
    $role->givePermissionTo(Module::Users->permission(PermissionAction::View));
    $user = User::factory()->create()->assignRole($role);

    expect($user->can('viewAny', User::class))->toBeTrue()
        ->and($user->can('create', User::class))->toBeFalse()
        ->and($user->can('update', $user))->toBeFalse()
        ->and($user->can('export', User::class))->toBeFalse();

    $role->givePermissionTo(Module::Users->permission(PermissionAction::Edit), Module::Users->permission(PermissionAction::Export));
    $user = $user->fresh();

    expect($user->can('update', $user))->toBeTrue()
        ->and($user->can('export', User::class))->toBeTrue()
        ->and($user->can('approve', $user))->toBeFalse();
});

it('denies everything to deactivated users and keeps them out of the panel', function () {
    $user = userWithRole('Owner/Director', ['is_active' => false]);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->can('viewAny', User::class))->toBeFalse();
});

it('keeps role-less users out of the panel', function () {
    expect(User::factory()->create()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('never allows users to be deleted, and protects roles in use', function () {
    $owner = userWithRole('Owner/Director');

    expect($owner->can('delete', $owner))->toBeFalse()
        ->and($owner->can('deleteAny', User::class))->toBeFalse();

    $inUse = Role::findByName('Owner/Director');
    $unused = Role::create(['name' => 'Unused']);

    expect($owner->can('delete', $inUse))->toBeFalse()
        ->and($owner->can('delete', $unused))->toBeTrue();
});

it('makes audit and login trails read-only for everyone', function () {
    $owner = userWithRole('Owner/Director');
    $log = AuditLog::first();

    expect($owner->can('viewAny', AuditLog::class))->toBeTrue()
        ->and($owner->can('update', $log ?? new AuditLog))->toBeFalse()
        ->and($owner->can('delete', $log ?? new AuditLog))->toBeFalse()
        ->and($owner->can('create', AuditLog::class))->toBeFalse()
        ->and(Module::AuditLogs->actions())->not->toContain(PermissionAction::Delete);

    expect(fn () => $log->update(['event' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});

it('audits user creation, changes and redacts secrets', function () {
    $actor = userWithRole('Owner/Director');
    $this->actingAs($actor);

    $user = User::factory()->create(['name' => 'Before', 'password' => 'Secret-Passw0rd!']);
    $user->update(['name' => 'After']);

    $created = AuditLog::where('event', 'created')->where('auditable_id', $user->id)
        ->where('auditable_type', $user->getMorphClass())->first();
    $updated = AuditLog::where('event', 'updated')->where('auditable_id', $user->id)
        ->where('auditable_type', $user->getMorphClass())->first();

    expect($created->user_id)->toBe($actor->id)
        ->and($created->new_values['password'])->toBe('[redacted]')
        ->and($updated->old_values)->toBe(['name' => 'Before'])
        ->and($updated->new_values)->toBe(['name' => 'After']);
});

it('records login, failed login and logout activity without passwords', function () {
    $user = userWithRole('Farm Manager');

    event(new Login('web', $user, false));
    event(new Failed('web', null, ['email' => 'nobody@azana.test', 'password' => 'hunter2']));
    event(new Logout('web', $user));

    expect(LoginActivity::orderBy('id')->pluck('event')->all())->toBe(['login', 'failed', 'logout'])
        ->and(LoginActivity::where('event', 'failed')->value('email'))->toBe('nobody@azana.test')
        ->and(json_encode(LoginActivity::all()))->not->toContain('hunter2')
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

it('requires 2FA for users holding a flagged role', function () {
    expect(userWithRole('Accountant')->requiresTwoFactor())->toBeTrue()
        ->and(userWithRole('Owner/Director')->requiresTwoFactor())->toBeTrue()
        ->and(userWithRole('Farm Worker')->requiresTwoFactor())->toBeFalse();

    $panel = Filament::getPanel('admin');
    $this->actingAs(userWithRole('Accountant'));
    expect($panel->isMultiFactorAuthenticationRequired())->toBeTrue();

    $this->actingAs(userWithRole('Farm Worker'));
    expect($panel->isMultiFactorAuthenticationRequired())->toBeFalse();
});

it('supports 2FA secrets that are encrypted at rest and hidden', function () {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    expect(DB::table('users')->where('id', $user->id)->value('app_authentication_secret'))->not->toContain('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->fresh()->toArray())->not->toHaveKey('app_authentication_secret');
});

it('creates users with roles through the Filament form and audits the role assignment', function () {
    $this->actingAs(userWithRole('Owner/Director'));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'New Vet',
            'email' => 'vet@azana.test',
            'password' => 'Str0ng-Passw0rd!',
            'roles' => [Role::findByName('Veterinarian')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::where('email', 'vet@azana.test')->first();

    expect($created->hasRole('Veterinarian'))->toBeTrue()
        ->and(AuditLog::where('event', 'roles_assigned')->where('auditable_id', $created->id)->exists())->toBeTrue();
});

it('rejects weak passwords', function () {
    $this->actingAs(userWithRole('Owner/Director'));

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => 'x@azana.test', 'password' => 'short', 'roles' => [Role::first()->id]])
        ->call('create')
        ->assertHasFormErrors(['password']);
});

it('edits the permission matrix of a role and audits the change', function () {
    $this->actingAs($owner = userWithRole('Owner/Director'));
    $role = Role::findByName('Farm Worker');

    Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['matrix' => [Module::Users->value => ['view', 'export']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toContain('users.export', 'users.view');

    $log = AuditLog::where('event', 'permissions_changed')->first();
    expect($log->user_id)->toBe($owner->id)
        ->and($log->old_values['permissions'])->not->toContain('users.view')
        ->and($log->new_values['permissions'])->toContain('users.export', 'users.view');
});

it('blocks users without the roles permission from the role pages', function () {
    $this->actingAs(userWithRole('Farm Worker'));

    $this->get(RoleResource::getUrl('index'))->assertForbidden();
});

it('lets the seeded panel require login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('gives Owner/Director every permission, even ones with no permission row or grant', function () {
    $this->actingAs($owner = userWithRole('Owner/Director'));

    // Simulate a module added by a later phase: the permission row does not exist yet.
    Permission::where('name', 'users.print')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($owner->can('users.print'))->toBeTrue()
        ->and($owner->can('audit-logs.export'))->toBeTrue();

    // Stripping the role's grants does not lock the Owner out.
    $owner->roles->first()->syncPermissions([]);
    expect($owner->fresh()->can('roles.edit'))->toBeTrue()
        ->and(RoleResource::canViewAny())->toBeTrue();
});

it('does not extend the Owner bypass to other roles or unrelated abilities', function () {
    expect(userWithRole('General Manager')->can('roles.edit'))->toBeFalse()
        ->and(userWithRole('Owner/Director')->can('some-unrelated.ability'))->toBeFalse();
});

it('locks out inactive owners', function () {
    $this->actingAs($owner = userWithRole('Owner/Director', ['is_active' => false]));

    expect($owner->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($owner->can('viewAny', User::class))->toBeFalse();
});

it('keeps hard policy rules in force for the Owner', function () {
    $owner = userWithRole('Owner/Director');
    $log = AuditLog::create(['event' => 'x']);

    expect($owner->can('delete', $owner))->toBeFalse()
        ->and($owner->can('update', $log))->toBeFalse()
        ->and($owner->can('delete', $log))->toBeFalse()
        ->and($owner->can('delete', Role::findByName('Owner/Director')))->toBeFalse();
});

it('stops non-owners assigning the Owner role', function () {
    $role = Role::create(['name' => 'User Admin']);
    $role->givePermissionTo('users.view', 'users.create', 'users.edit');
    $this->actingAs(User::factory()->create()->assignRole($role));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Sneaky', 'email' => 'sneaky@azana.test', 'password' => 'Str0ng-Passw0rd!',
            'roles' => [Role::findByName('Owner/Director')->id],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles']);

    expect(User::where('email', 'sneaky@azana.test')->exists())->toBeFalse();
});

it('lets owners assign the Owner role', function () {
    $this->actingAs(userWithRole('Owner/Director'));

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Second Owner', 'email' => 'owner2@azana.test', 'password' => 'Str0ng-Passw0rd!',
            'roles' => [Role::findByName('Owner/Director')->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::firstWhere('email', 'owner2@azana.test')->hasRole('Owner/Director'))->toBeTrue();
});

it('stops non-owners editing Owner accounts or the Owner role', function () {
    $role = Role::create(['name' => 'User Admin']);
    $role->givePermissionTo('users.edit', 'roles.edit');
    $admin = User::factory()->create()->assignRole($role);
    $owner = userWithRole('Owner/Director');

    expect($admin->can('update', $owner))->toBeFalse()
        ->and($admin->can('update', User::factory()->create()))->toBeTrue()
        ->and($admin->can('update', Role::findByName('Owner/Director')))->toBeFalse()
        ->and($owner->can('update', Role::findByName('Owner/Director')))->toBeTrue();
});

it('protects the Owner role from rename and deletion', function () {
    $owner = Role::findByName('Owner/Director');

    expect(fn () => $owner->update(['name' => 'Renamed']))->toThrow(LogicException::class)
        ->and(fn () => $owner->delete())->toThrow(LogicException::class)
        ->and(userWithRole('Owner/Director')->can('delete', $owner))->toBeFalse();

    $fresh = Role::findByName('Owner/Director');
    $fresh->update(['description' => 'Still editable']);
    expect($fresh->fresh()->description)->toBe('Still editable');
});

it('can seed demo users repeatedly', function () {
    $this->seed();
    $this->seed();

    expect(User::where('email', 'like', '%@azana.test')->count())->toBe(12);
});
