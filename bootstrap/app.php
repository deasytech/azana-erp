<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureMobileAccess;
use App\Support\ErrorTally;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Outside the `web` group: no session/CSRF, so it can still report 503 when the session store is down.
            Route::get('/health', HealthController::class)->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
        $middleware->alias(['mobile' => EnsureMobileAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(fn (Throwable $e) => ErrorTally::record($e));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
