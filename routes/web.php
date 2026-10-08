<?php

use App\Http\Controllers\AnimalLookupController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\Site\SiteController;
use Illuminate\Support\Facades\Route;

// The public website. With WEBSITE_HOST set it answers only on that host (the ERP lives on ERP_HOST); unset, on any host.
Route::domain(config('website.host'))->name('site.')->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/about', [SiteController::class, 'about'])->name('about');
    Route::get('/operations', [SiteController::class, 'operations'])->name('operations');
    Route::get('/sustainability', [SiteController::class, 'sustainability'])->name('sustainability');
    Route::get('/products', [SiteController::class, 'products'])->name('products');
    Route::get('/products/{slug}', [SiteController::class, 'product'])->where('slug', '[a-z0-9-]+')->name('product');
    Route::get('/contact', [SiteController::class, 'contact'])->name('contact');
    Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
    Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
    Route::get('/{kind}', [SiteController::class, 'section'])->where('kind', 'pigs|semen|meat')->name('section');
});

Route::get('/animals/lookup/{code}', AnimalLookupController::class)
    ->middleware(['auth', 'throttle:60,1'])
    ->where('code', '.*')
    ->name('animals.lookup');

Route::get('/docs', ApiDocsController::class)->name('api.docs');
