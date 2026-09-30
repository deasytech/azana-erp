<?php

namespace App\Providers;

use App\Domain\Animal\Models as A;
use App\Domain\Breeding\Models as B;
use App\Domain\Farm\Models as M;
use App\Domain\Litter\Models as L;
use App\Enums\Module;
use App\Models\User;
use App\Policies\AnimalPhotoPolicy;
use App\Policies\AnimalPolicy;
use App\Policies\BreedingPolicy;
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
        foreach ([A\Animal::class, A\AnimalIdentifier::class, A\AnimalMovement::class, A\WeightRecord::class, A\AnimalStatusHistory::class, A\AnimalParentage::class] as $model) {
            Gate::policy($model, AnimalPolicy::class);
        }
        foreach ([B\HeatEvent::class, B\BreedingService::class, B\PregnancyCheck::class, B\Farrowing::class, L\Litter::class, L\Piglet::class, L\LitterLoss::class, L\WeaningRecord::class] as $model) {
            Gate::policy($model, BreedingPolicy::class);
        }
        Gate::policy(A\AnimalPhoto::class, AnimalPhotoPolicy::class);
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
