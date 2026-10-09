<?php

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Filament\Pages\Dashboard;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** Routes anyone may open: the public website, the API documentation, the load-balancer health check, the sign-in endpoints. */
const PUBLIC_ROUTES = ['site.', 'api.docs', 'health', 'up', 'storage.', 'filament.admin.auth.login', 'filament.admin.auth.password-reset', 'filament.admin.auth.email-verification', 'login', 'livewire.', 'sanctum.', 'ignition.'];

/** Only the real sign-in checks count: the `auth` alias, Laravel's Authenticate and the panel's own (not, say, AuthenticateSession). */
const SIGN_IN_MIDDLEWARE = '/^(?:auth(?::.+)?|Illuminate\\\\Auth\\\\Middleware\\\\Authenticate(?::.+)?|Filament\\\\Http\\\\Middleware\\\\Authenticate)$/';

it('puts every route except the public ones behind a sign-in', function () {
    $open = collect(Route::getRoutes()->getRoutes())->reject(function (LaravelRoute $route) {
        $name = (string) $route->getName();
        $middleware = collect($route->gatherMiddleware())->map(fn ($m) => is_string($m) ? $m : 'closure');

        // Filament panel routes are guarded by the panel's own authentication middleware.
        return $middleware->contains(fn ($m) => preg_match(SIGN_IN_MIDDLEWARE, $m) === 1)
            || collect(PUBLIC_ROUTES)->contains(fn ($p) => str_starts_with($name, $p))
            || $route->uri() === 'api/v1/auth/login';
    })->map(fn (LaravelRoute $r) => implode('|', $r->methods()).' '.$r->uri())->values()->all();

    // Framework endpoints that check the user themselves: Livewire (every component authorizes its own actions), Filament's file
    // downloads (they abort unless the signed-in user owns the file) and Laravel's health route.
    $open = array_values(array_filter($open, fn (string $r) => ! preg_match('#^\S+ (?:(?:livewire-[0-9a-f]+/)|(?:filament/(?:exports|imports)/)|(?:up$))#', $r)));

    expect(collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1') && $r->uri() !== 'api/v1/auth/login')
        ->every(fn ($r) => in_array('auth:sanctum', $r->gatherMiddleware(), true)))->toBeTrue('every mobile API route needs a token');

    expect(array_values(array_filter($open, fn (string $r) => ! preg_match('#^(GET\|HEAD|HEAD\|GET) (/|about|operations|sustainability|products|products/\{slug}|contact|sitemap\.xml|robots\.txt|\{kind})$#', $r))))->toBe([]);
});

it('sends someone who is not signed in away from every ERP page', function () {
    $panel = Filament::getPanel('admin');

    foreach ($panel->getResources() as $resource) {
        $this->get($resource::getUrl('index', isAbsolute: false))->assertRedirect();
    }

    foreach ($panel->getPages() as $page) {
        $this->get($page::getUrl(isAbsolute: false))->assertRedirect();
    }
});

it('registers a policy for the model behind every Filament resource', function () {
    $missing = collect(Filament::getPanel('admin')->getResources())
        ->map(fn ($resource) => $resource::getModel())
        ->reject(fn ($model) => Gate::getPolicyFor($model) !== null)
        ->values()->all();

    expect($missing)->toBe([]);
});

it('keeps every resource closed to a user who holds no permission', function () {
    $nobody = User::factory()->create();
    $this->actingAs($nobody);
    $open = [];

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $resource::canViewAny() && $open[] = $resource;
        $resource::canCreate() && $open[] = "{$resource} (create)";
    }

    expect($open)->toBe([]);
});

it('opens every resource to the owner', function () {
    $this->actingAs(owner());
    $closed = collect(Filament::getPanel('admin')->getResources())->reject(fn ($resource) => $resource::canViewAny())->values()->all();

    expect($closed)->toBe([]);
});

it('keeps every page except the home page closed to a user who holds no permission', function () {
    $this->actingAs(User::factory()->create());
    $open = collect(Filament::getPanel('admin')->getPages())
        ->reject(fn ($page) => $page === Dashboard::class)
        ->filter(fn ($page) => $page::canAccess())->values()->all();

    expect($open)->toBe([]);
});

it('gives no policy-guarded model to a user without permissions', function () {
    $nobody = User::factory()->create();
    $leaks = [];

    foreach (collect(Gate::policies())->keys() as $model) {
        foreach (['viewAny', 'create'] as $ability) {
            Gate::forUser($nobody)->allows($ability, $model) && $leaks[] = "{$model}::{$ability}";
        }
    }

    expect($leaks)->toBe([]);
});

it('never lets even the owner delete users or edit the audit trail', function () {
    $owner = owner();

    expect($owner->can('delete', User::factory()->create()))->toBeFalse()
        ->and($owner->can('update', new AuditLog))->toBeFalse()
        ->and($owner->can('delete', new AuditLog))->toBeFalse();
});

it('only ever names real modules and actions in the permission table', function () {
    $valid = collect(Module::cases())->flatMap(fn (Module $m) => collect(PermissionAction::cases())->map(fn ($a) => "{$m->value}.{$a->value}"));

    expect(Permission::pluck('name')->reject(fn ($name) => $valid->contains($name))->values()->all())->toBe([]);
});

it('keeps money, administration and approval powers away from the operational roles', function (string $role) {
    $permissions = Role::findByName($role)->permissions->pluck('name');
    $sensitive = ['finance.', 'users.', 'roles.', 'audit', 'data-imports.', 'backups.'];

    expect($permissions->filter(fn ($p) => collect($sensitive)->contains(fn ($s) => str_starts_with($p, $s)))->values()->all())->toBe([]);
})->with(['Farm Worker', 'Veterinarian', 'Breeding Manager', 'Nutritionist', 'Slaughter Manager', 'Store Officer']);

it('never lets a Farm Worker approve, delete or export anything', function () {
    $granted = Role::findByName('Farm Worker')->permissions->pluck('name')
        ->filter(fn ($p) => preg_match('/\.(approve|delete|export)$/', $p))->values()->all();

    expect($granted)->toBe([]);
});

it('requires two-factor sign-in for the roles that handle money, semen release and everything', function () {
    foreach (['Owner/Director', 'General Manager', 'Accountant', 'Semen Laboratory Manager'] as $name) {
        expect(Role::findByName($name)->requires_two_factor)->toBeTrue("{$name} must use two-factor");
    }
});
