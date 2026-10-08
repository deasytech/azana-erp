<?php

use App\Domain\Health\Actions\RecordVeterinaryVisit;
use App\Filament\Resources\VeterinaryVisits\Pages\ListVeterinaryVisits;
use Livewire\Livewire;

it('filters a list to the chosen date range', function () {
    $this->actingAs(owner());
    app(RecordVeterinaryVisit::class)(now()->subDays(30), 'Old herd check');
    app(RecordVeterinaryVisit::class)(now()->subDay(), 'Recent herd check');

    Livewire::test(ListVeterinaryVisits::class)
        ->assertSee('Old herd check')->assertSee('Recent herd check')
        ->filterTable('visited_on_range', ['from' => now()->subDays(7)->toDateString(), 'until' => null])
        ->assertSee('Recent herd check')->assertDontSee('Old herd check')
        ->filterTable('visited_on_range', ['from' => null, 'until' => now()->subDays(7)->toDateString()])
        ->assertSee('Old herd check')->assertDontSee('Recent herd check');
});
