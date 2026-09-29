<?php

namespace App\Providers;

use App\Domain\Farm\Models as M;
use App\Enums\Module;
use App\Models\User;
use App\Policies\FarmStructurePolicy;
use App\Policies\MasterDataPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\SettingsPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Owner/Director implicitly holds every "{module}.{action}" permission. Only permission
        // checks are bypassed: policy rules (no user deletion, read-only audit trails) still apply.
        foreach ([M\Farm::class, M\ProductionUnit::class, M\Building::class, M\Room::class, M\Pen::class, M\Location::class] as $model) {
            Gate::policy($model, FarmStructurePolicy::class);
        }
        foreach ([M\Breed::class, M\GeneticLine::class, M\UnitOfMeasure::class, M\LookupValue::class] as $model) {
            Gate::policy($model, MasterDataPolicy::class);
        }
        Gate::policy(M\FarmSetting::class, SettingsPolicy::class);
        Gate::policy(M\PriceList::class, PriceListPolicy::class);
        Gate::policy(M\PriceListItem::class, PriceListPolicy::class);

        Gate::before(function (User $user, string $ability): ?bool {
            $module = strstr($ability, '.', true);

            return $module !== false && Module::tryFrom($module) && $user->isOwner() ? true : null;
        });

        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));

    }
}
