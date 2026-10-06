<?php

use App\Domain\Semen\Actions\ExpireSemenBatches;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ExpireSemenBatches::class)())->daily()->name('expire-semen-batches')->withoutOverlapping();
