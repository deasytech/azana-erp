<?php

namespace App\Providers;

use App\Enums\Module;
use App\Models\User;
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
